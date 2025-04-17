<?php

namespace App\Applications\Praxedo\scripts\Activities\Service;

use App\Applications\Praxedo\Common\PraxedoClient;
use App\Applications\Wavesoft\scripts\activities\Service\WavesoftActivitiesService;
use App\Service\PraxedoApiLogger;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Exception\ORMException;
use Exception;
use Psr\Log\LoggerInterface;
use SoapFault;

class PraxedoActivitiesService
{
    private const ACTIVITY_TYPES = [
        'MO-FORM' => 'Formation',
        'Int' => 'Montage',
        'NET_CAM' => 'Nettoyage / Rangement Camion',
        'N2F' => 'Notes de Frais',
        'PLANNING' => 'Organisation Planning',
        'REP' => 'Repas',
        'REUNION_TECH' => 'Réunion Technique',
        'SALON' => 'Salon',
        'SAV_SUPPORT' => 'SAV (Support)',
        'SAV_TERRAIN' => 'SAV (Terrain)',
        'TRAJET' => 'Trajet',
        'REDAC_TECH' => 'Rédaction Technique',
        'COMMERCE' => 'Commerce',
        'SERVICE_REPAR' => 'Service Réparation (Venon)',
        'AIDE_COMMERCE' => 'Commerce',
        'BUREAU' => 'Autres',
    ];

    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 1;
    private const RATE_LIMIT_DELAY = 1;
    private const BATCH_SIZE = 500;

    private PraxedoClient $client;
    private PraxedoClient $businessEventClient;
    private ?string $executionId = null;

    /**
     * @throws SoapFault
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly WavesoftActivitiesService $wavesoftActivitiesService,
        private readonly PraxedoApiLogger $praxedoApiLogger,
    ) {
        $this->client = new PraxedoClient(
            sprintf('%s/ActivityManager?wsdl', $_ENV['PRAXEDO_BASE_URL']),
            $_ENV['PRAXEDO_LOGIN'],
            $_ENV['PRAXEDO_PASSWORD'],
            $this->logger,
            [
                'trace' => true,
                'exceptions' => true,
            ]
        );

        $this->businessEventClient = new PraxedoClient(
            sprintf('%s/BusinessEventManager?wsdl', $_ENV['PRAXEDO_BASE_URL']),
            $_ENV['PRAXEDO_LOGIN'],
            $_ENV['PRAXEDO_PASSWORD'],
            $this->logger,
            [
                'trace' => true,
                'exceptions' => true,
                'mtom' => true,
            ]
        );
    }

    public function setExecutionId(string $executionId): void
    {
        $this->executionId = $executionId;
    }

    /**
     * Synchronise les activités avec la base Wavesoft.
     *
     * @throws Exception
     * @throws ORMException
     */
    public function synchronizeActivities(\DateTime $startDate, \DateTime $endDate): array
    {
        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'errors' => 0,
            'activities' => 0,
            'error_details' => []
        ];

        try {
            $result = $this->getActivities($startDate, $endDate);
            if (isset($result->return->entities)) {
                $activities = is_array($result->return->entities)
                    ? $result->return->entities
                    : [$result->return->entities];

                $detailsToFetch = [];
                foreach ($activities as $activity) {
                    if (isset($activity->latitude) && isset($activity->longitude) && !isset($activity->activityTypeId)) {
                        continue;
                    }

                    if (!isset($activity->id) || !isset($activity->activityTypeId) || !isset($activity->agentId)) {
                        $missingProps = [];
                        foreach (['id', 'activityTypeId', 'agentId'] as $prop) {
                            if (!isset($activity->$prop)) {
                                $missingProps[] = $prop;
                            }
                        }
                        ++$stats['errors'];
                        $stats['error_details'][] = [
                            'type' => 'invalid_activity',
                            'activity_id' => $activity->id ?? 'non défini',
                            'missing_properties' => $missingProps,
                            'available_properties' => array_keys(get_object_vars($activity))
                        ];
                        continue;
                    }

                    if ('Autres' === (self::ACTIVITY_TYPES[$activity->activityTypeId] ?? 'Autres') && !empty($activity->businessEventId)) {
                        $detailsToFetch[] = $activity->businessEventId;
                    }
                }
                $allDetails = [];
                if (!empty($detailsToFetch)) {
                    $allDetails = $this->getActivityDetails($detailsToFetch);
                }
                foreach ($activities as $activity) {
                    try {
                        ++$stats['total'];

                        if (isset($activity->deleted) && $activity->deleted) {
                            $this->wavesoftActivitiesService->deleteActivity($activity->id);
                            ++$stats['deleted'];
                            continue;
                        }

                        if (!isset($activity->activityEnd) || !isset($activity->activityTypeId)) {
                            continue;
                        }

                        if (!isset($activity->id) || !isset($activity->agentId) || !isset($activity->activityStart) || !isset($activity->lastUpdate)) {
                            $missingProperties = [];
                            foreach (['id', 'agentId', 'activityStart', 'lastUpdate'] as $prop) {
                                if (!isset($activity->$prop)) {
                                    $missingProperties[] = $prop;
                                }
                            }
                            ++$stats['errors'];
                            $stats['error_details'][] = [
                                'type' => 'missing_properties',
                                'activity_id' => $activity->id ?? 'unknown',
                                'missing_properties' => $missingProperties
                            ];
                            continue;
                        }

                        $activityData = [
                            'TYPE_ACTIVITE' => self::ACTIVITY_TYPES[$activity->activityTypeId] ?? 'Autres',
                            'ID_AGENT' => $activity->agentId,
                            'DATE_DEBUT' => (new \DateTime($activity->activityStart))->format('d/m/Y H:i:s.v'),
                            'DATE_FIN' => (new \DateTime($activity->activityEnd))->format('d/m/Y H:i:s.v'),
                            'DATE_UPDATE' => (new \DateTime())->format('d/m/Y H:i:s.v'),
                            'DATE_UPDATE_PRAXEDO' => (new \DateTime($activity->lastUpdate))->format('d/m/Y H:i:s.v'),
                            'INTER_PRAXEDO' => $activity->businessEventId ?? '',
                            'ID_ACTIVITE_PRAXEDO' => $activity->id,
                            'DETAILS' => '',
                        ];

                        if ('Autres' === $activityData['TYPE_ACTIVITE'] && !empty($activityData['INTER_PRAXEDO'])) {
                            $activityData['DETAILS'] = $allDetails[$activity->businessEventId] ?? 'Pas encore finit...';
                            ++$stats['activities'];
                        }

                        $result = $this->wavesoftActivitiesService->upsertActivity($activityData);

                        if ($result) {
                            ++$stats['updated'];
                        } else {
                            ++$stats['created'];
                        }
                    } catch (Exception $e) {
                        $this->logger->error('Erreur traitement activité: '.$e->getMessage());
                        ++$stats['errors'];
                        $stats['error_details'][] = [
                            'type' => 'activity_processing_error',
                            'activity_id' => $activity->id ?? 'non défini',
                            'message' => $e->getMessage()
                        ];
                    }
                }
            }
            return $stats;
        } catch (Exception $e) {
            $this->logger->error('Erreur générale: '.$e->getMessage());
            throw $e;
        } catch (ORMException $e) {
            $this->logger->error('Erreur ORM: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère les détails d'une ou plusieurs interventions.
     *
     * @param string|array $interventionIds Un ID ou un tableau d'IDs d'interventions
     *
     * @return array Si un seul ID est fourni, retourne ['details' => string], sinon retourne [id => details]
     * @throws ORMException
     */
    private function getActivityDetails(string|array $interventionIds): array
    {
        $ids = is_array($interventionIds) ? $interventionIds : [$interventionIds];
        $allDetails = [];

        foreach (array_chunk($ids, 50) as $chunk) {
            $retries = 0;
            while ($retries < self::MAX_RETRIES) {
                try {
                    $startTime = microtime(true);
                    $params = new \stdClass();
                    $params->requestedEvents = $chunk;
                    $params->options = [
                        'key' => 'businessEvent.populate.completionData.fields'
                    ];

                    $result = $this->businessEventClient->call('getEvents', $params);
                    $duration = microtime(true) - $startTime;

                    if ($this->executionId) {
                        $this->praxedoApiLogger->logApiCall(
                            $this->executionId,
                            '/BusinessEventManager/getEvents',
                            'POST',
                            json_encode($params),
                            json_encode($result),
                            200,
                            $duration
                        );
                    }

                    if (!isset($result->return)) {
                        throw new Exception('Réponse API invalide');
                    }
                    if (isset($result->return->resultCode) && 0 !== $result->return->resultCode) {
                        throw new Exception($result->return->message ?? 'Erreur API sans message');
                    }

                    if (!isset($result->return->entities)) {
                        break;
                    }

                    $entities = is_array($result->return->entities)
                        ? $result->return->entities
                        : [$result->return->entities];

                    foreach ($entities as $intervention) {
                        if (!isset($intervention->id)) {
                            continue;
                        }

                        $details = 'Pas encore finit...';
                        if (isset($intervention->completionData->fields)) {
                            $fields = is_array($intervention->completionData->fields) 
                                ? $intervention->completionData->fields 
                                : [$intervention->completionData->fields];

                            foreach ($fields as $field) {
                                if (isset($field->id) && 'DETAIL_BUREAU' === $field->id) {
                                    $details = isset($field->value) ? ($field->value ?: 'Pas encore finit...') : 'Pas encore finit...';
                                    break;
                                }
                            }
                        }
                        $allDetails[$intervention->id] = $details;
                    }

                    break;
                } catch (Exception $e) {
                    ++$retries;

                    if (str_contains($e->getMessage(), 'Rate Limit reached')) {
                        $this->logger->warning('Rate limit atteint, pause de {delay} secondes', [
                            'delay' => self::RATE_LIMIT_DELAY,
                        ]);
                        sleep(self::RATE_LIMIT_DELAY);
                        continue;
                    }

                    if ($retries >= self::MAX_RETRIES) {
                        break;
                    }

                    $this->logger->warning('Erreur API, nouvelle tentative dans {delay} secondes', [
                        'error' => $e->getMessage(),
                        'retry' => $retries,
                        'delay' => self::RETRY_DELAY,
                    ]);
                    sleep(self::RETRY_DELAY);
                }
            }
        }

        return is_string($interventionIds)
            ? ['details' => $allDetails[$interventionIds] ?? 'Pas encore finit...']
            : $allDetails;
    }

    /**
     * Récupère les activités depuis Praxedo par tranches de 24h.
     *
     * @throws Exception|ORMException
     */
    private function getActivities(\DateTime $startDate, \DateTime $endDate): \stdClass
    {
        try {
            $this->logger->info('Récupération des activités', [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end' => $endDate->format('Y-m-d H:i:s'),
            ]);

            $allActivities = new \stdClass();
            $allActivities->return = new \stdClass();
            $allActivities->return->entities = [];

            $currentStart = clone $startDate;
            $currentStart->setTimezone(new \DateTimeZone('UTC'));
            $currentStart->setTime(0, 0, 0);

            $currentEnd = clone $endDate;
            $currentEnd->setTimezone(new \DateTimeZone('UTC'));
            if ('23:59' === $currentEnd->format('H:i')) {
                $currentEnd->setTime(23, 59, 59);
            }

            $pendingRequests = [];

            while ($currentStart <= $currentEnd) {
                $dayEnd = clone $currentStart;
                $dayEnd->setTime(23, 59, 59);

                if ($dayEnd > $currentEnd) {
                    $dayEnd = clone $currentEnd;
                }

                $request = new \stdClass();
                $request->request = new \stdClass();
                $request->request->startDate = $currentStart->format('Y-m-d\TH:i:s.000\Z');
                $request->request->endDate = $dayEnd->format('Y-m-d\TH:i:s.999\Z');

                $pendingRequests[] = [
                    'request' => $request,
                    'start' => clone $currentStart,
                    'end' => clone $dayEnd,
                ];

                if (10 === count($pendingRequests) || $dayEnd >= $currentEnd) {
                    foreach ($pendingRequests as $pendingRequest) {
                        $retries = 0;
                        $success = false;

                        while (!$success && $retries < self::MAX_RETRIES) {
                            try {
                                $startTime = microtime(true);
                                $params = new \stdClass();
                                $params->request = $pendingRequest['request']->request;
                                $params->batchSize = self::BATCH_SIZE;
                                $params->offset = 0;
                                $params->options = [];

                                $result = $this->client->call('searchActivities', $params);
                                $duration = microtime(true) - $startTime;

                                if ($this->executionId) {
                                    $this->praxedoApiLogger->logApiCall(
                                        $this->executionId,
                                        '/ActivityManager/searchActivities',
                                        'POST',
                                        json_encode($params),
                                        json_encode($result),
                                        200,
                                        $duration
                                    );
                                }

                                if (!$result || !isset($result->return)) {
                                    throw new Exception('Réponse API invalide');
                                }

                                if (isset($result->return->resultCode) && 0 !== $result->return->resultCode) {
                                    throw new Exception($result->return->message ?? 'Erreur API sans message');
                                }

                                $success = true;

                                if (isset($result->return->entities)) {
                                    $entities = is_array($result->return->entities)
                                        ? $result->return->entities
                                        : [$result->return->entities];

                                    foreach ($entities as $entity) {
                                        $lastUpdate = new \DateTime($entity->lastUpdate);
                                        $lastUpdate->setTimezone(new \DateTimeZone('UTC'));

                                        if ($lastUpdate >= $currentStart && $lastUpdate <= $currentEnd) {
                                            $allActivities->return->entities[] = $entity;
                                        }
                                    }
                                }
                            } catch (Exception $e) {
                                ++$retries;

                                if (str_contains($e->getMessage(), 'Rate Limit reached')) {
                                    $this->logger->warning('Rate limit atteint, pause de {delay} secondes', [
                                        'delay' => self::RATE_LIMIT_DELAY,
                                    ]);
                                    sleep(self::RATE_LIMIT_DELAY);
                                } else {
                                    if ($retries < self::MAX_RETRIES) {
                                        sleep(self::RETRY_DELAY);
                                    }
                                }

                                if ($retries >= self::MAX_RETRIES) {
                                    throw new Exception('Nombre maximum de tentatives atteint: '.$e->getMessage());
                                }
                            }
                        }
                    }

                    $pendingRequests = [];
                }

                $currentStart->modify('+1 day')->setTime(0, 0, 0);
            }

            return $allActivities;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la récupération des activités: '.$e->getMessage());
            throw $e;
        }
    }
}
