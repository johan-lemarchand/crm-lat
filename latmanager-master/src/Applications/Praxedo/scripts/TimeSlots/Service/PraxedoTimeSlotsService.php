<?php

namespace App\Applications\Praxedo\scripts\TimeSlots\Service;

use App\Applications\Praxedo\Common\PraxedoClient;
use App\Applications\Praxedo\Common\PraxedoException;
use App\Applications\Wavesoft\scripts\timeslots\Service\WavesoftTimeSlotsService;
use App\Service\PraxedoApiLogger;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Exception\ORMException;
use Exception;
use Psr\Log\LoggerInterface;
use SoapFault;

class PraxedoTimeSlotsService
{
    private PraxedoClient $client;
    private ?string $executionId = null;

    /**
     * @throws SoapFault
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly WavesoftTimeSlotsService $wavesoftTimeSlotsService,
        private readonly PraxedoApiLogger $praxedoApiLogger,
    ) {
        $this->client = new PraxedoClient(
            sprintf('%s/TimeSlotManager?wsdl', $_ENV['PRAXEDO_BASE_URL']),
            $_ENV['PRAXEDO_LOGIN'],
            $_ENV['PRAXEDO_PASSWORD'],
            $this->logger,
            [
                'trace' => true,
                'exceptions' => true,
            ]
        );
    }

    public function setExecutionId(string $executionId): void
    {
        $this->executionId = $executionId;
    }

    /**
     * Synchronise les créneaux avec la base Wavesoft.
     */
    public function synchronizeTimeSlots(\DateTime $startDate, \DateTime $endDate): array
    {
        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {
            $timeSlots = $this->searchUnavailableTimeSlots($startDate, $endDate);

            foreach ($timeSlots as $timeSlot) {
                try {
                    ++$stats['total'];

                    if (!is_array($timeSlot) && !is_object($timeSlot)) {
                        ++$stats['errors'];
                        $stats['error_details'][] = [
                            'type' => 'invalid_timeslot',
                            'message' => 'Le créneau n\'est ni un tableau ni un objet'
                        ];
                        continue;
                    }

                    $data = is_object($timeSlot) ? get_object_vars($timeSlot) : $timeSlot;

                    $requiredFields = ['id', 'name', 'technicianId', 'period'];
                    $missingFields = [];
                    foreach ($requiredFields as $field) {
                        if (!isset($data[$field])) {
                            $missingFields[] = $field;
                        }
                    }

                    if (!empty($missingFields)) {
                        ++$stats['errors'];
                        $stats['error_details'][] = [
                            'type' => 'missing_fields',
                            'timeslot_id' => $data['id'] ?? 'N/A',
                            'message' => 'Champs manquants: ' . implode(', ', $missingFields)
                        ];
                        continue;
                    }

                    if (!is_array($data['technicianId']) || empty($data['technicianId'])) {
                        ++$stats['errors'];
                        $stats['error_details'][] = [
                            'type' => 'invalid_technician',
                            'timeslot_id' => $data['id'],
                            'message' => 'Technicien manquant ou invalide'
                        ];
                        continue;
                    }

                    $technicianId = $data['technicianId'][0];

                    if (!is_array($data['period']) || empty($data['period'])) {
                        ++$stats['errors'];
                        $stats['error_details'][] = [
                            'type' => 'invalid_period',
                            'timeslot_id' => $data['id'],
                            'message' => 'Période manquante ou invalide',
                            'period' => $data['period'] ?? null
                        ];
                        continue;
                    }

                    $period = is_object($data['period'][0]) ? get_object_vars($data['period'][0]) : $data['period'][0];
                    
                    if (!isset($period['startDate'], $period['endDate'])) {
                        ++$stats['errors'];
                        $stats['error_details'][] = [
                            'type' => 'invalid_period',
                            'timeslot_id' => $data['id'],
                            'message' => 'Dates de début et/ou de fin manquantes',
                            'period' => $period
                        ];
                        continue;
                    }

                    try {
                        $startDateTime = new \DateTime($period['startDate']);
                        $endDateTime = new \DateTime($period['endDate']);

                        if ($startDateTime > $endDateTime) {
                            ++$stats['errors'];
                            $stats['error_details'][] = [
                                'type' => 'invalid_dates',
                                'timeslot_id' => $data['id'],
                                'message' => 'Date de début postérieure à la date de fin',
                                'details' => [
                                    'start' => $period['startDate'],
                                    'end' => $period['endDate']
                                ]
                            ];
                            continue;
                        }
                    } catch (Exception $e) {
                        ++$stats['errors'];
                        $stats['error_details'][] = [
                            'type' => 'invalid_date_format',
                            'timeslot_id' => $data['id'],
                            'message' => 'Format de date invalide: ' . $e->getMessage()
                        ];
                        continue;
                    }

                    $timeSlotData = [
                        'TYPE_CRENEAU' => $data['name'],
                        'ID_AGENT' => $technicianId,
                        'DATE_DEBUT' => $this->formatPraxedoDate($period['startDate']),
                        'DATE_FIN' => $this->formatPraxedoDate($period['endDate']),
                        'DATE_UPDATE' => (new \DateTime())->format('d/m/Y H:i:s.v'),
                        'ID_CRENEAU_PRAXEDO' => 'CR_'.$data['id'],
                        'COMPLETE_DAY' => isset($data['dayLength']) && $data['dayLength'] ? 'O' : 'N',
                        'ISDELETED' => 'N'
                    ];

                    $result = $this->wavesoftTimeSlotsService->upsertTimeSlot($timeSlotData);

                    if ($result) {
                        ++$stats['updated'];
                    } else {
                        ++$stats['created'];
                    }
                } catch (Exception $e) {
                    ++$stats['errors'];
                    $stats['error_details'][] = [
                        'type' => 'processing_error',
                        'timeslot_id' => $data['id'] ?? 'unknown',
                        'message' => $e->getMessage()
                    ];
                }
            }

            $twoMonthsAgo = clone $startDate;
            $twoMonthsAgo->modify('-2 months');

            $existingTimeSlots = $this->wavesoftTimeSlotsService->getTimeSlots($twoMonthsAgo);
            $timeSlotIds = array_column($existingTimeSlots, 'ID_CRENEAU_PRAXEDO');
            
            $totalSlots = count($timeSlotIds);
            if ($totalSlots === 0) {
                return $stats;
            }

            $batchSize = 100;
            $numBatches = max(1, ceil($totalSlots / $batchSize));
            $remainingSlots = $totalSlots;
            
            for ($i = 0; $i < $numBatches; $i++) {
                try {
                    $start = $i * $batchSize;
                    $slotsInBatch = min($batchSize, $remainingSlots);
                    $currentBatch = array_slice($timeSlotIds, $start, $slotsInBatch);
                    $remainingSlots -= $slotsInBatch;
                    
                    $praxedoIds = array_map(function ($id) {
                        return substr($id, 3);
                    }, $currentBatch);

                    $params = new \stdClass();
                    $params->unavailableTimeSlots = $praxedoIds;
                    $params->options = [];

                    $startTime = microtime(true);
                    $result = $this->client->call('getUnavailableTimeSlot', $params);
                    $duration = microtime(true) - $startTime;

                    if ($this->executionId) {
                        $this->praxedoApiLogger->logApiCall(
                            $this->executionId,
                            '/TimeSlotManager/getUnavailableTimeSlot',
                            'POST',
                            json_encode($params),
                            json_encode($result),
                            200,
                            $duration
                        );
                    }

                    if (isset($result->return->resultCode) && $result->return->resultCode !== 0) {
                        if ($result->return->resultCode === 50) {
                            $this->wavesoftTimeSlotsService->markTimeSlotsAsDeleted($currentBatch);
                            $stats['deleted'] += count($currentBatch);
                            continue;
                        }
                        
                        if ($result->return->resultCode === 51) {
                            throw new Exception('Trop d\'IDs dans la requête (maximum 100)');
                        }
                    }

                    $entities = [];
                    if (isset($result->return->entities)) {
                        $entities = is_array($result->return->entities) ? $result->return->entities : [$result->return->entities];
                    }

                    // Si aucune entité n'est retournée, cela signifie que tous les créneaux ont été supprimés
                    if (empty($entities)) {
                        $this->wavesoftTimeSlotsService->markTimeSlotsAsDeleted($currentBatch);
                        $stats['deleted'] += count($currentBatch);
                        continue;
                    }

                    $idsToDelete = $this->compareCreneaux($currentBatch, $entities);

                    if ($idsToDelete !== '()') {
                        $idsToDeleteArray = array_map(
                            function($id) { return trim($id, '\'"'); },
                            explode(',', trim($idsToDelete, '()'))
                        );
                        $this->wavesoftTimeSlotsService->markTimeSlotsAsDeleted($idsToDeleteArray);
                        $stats['deleted'] += count($idsToDeleteArray);
                    }
                } catch (Exception $e) {
                    ++$stats['errors'];
                    $stats['error_details'][] = [
                        'type' => 'deletion_check_error',
                        'timeslot_ids' => $currentBatch,
                        'message' => $e->getMessage()
                    ];
                }
            }
            return $stats;
        } catch (Exception | ORMException $e) {
            throw new PraxedoException(sprintf('Erreur lors de la synchronisation des créneaux: %s', $e->getMessage()), $e->getCode(), $e);
        }
    }

    /**
     * Récupère les créneaux non disponibles depuis Praxedo.
     *
     * @return array Liste des créneaux
     *
     * @throws Exception|ORMException
     */
    private function searchUnavailableTimeSlots(\DateTime $startDate, \DateTime $endDate): array
    {
        try {
            $request = new \stdClass();
            $request->request = new \stdClass();
            $request->request->dateConstraints = [
                (object) [
                    'name' => 'lastModificationDate',
                    'dateRange' => [
                        $startDate->format('Y-m-d\TH:i:s.000\Z'),
                        $endDate->format('Y-m-d\TH:i:s.999\Z'),
                    ],
                ],
            ];

            $allEntities = [];
            $firstResultIndex = 0;
            $batchSize = 50;
            $maxIterations = 200;
            $iteration = 0;

            do {
                $iteration++;
                if ($iteration > $maxIterations) {
                    break;
                }

                $params = new \stdClass();
                $params->request = $request->request;
                $params->batchSize = $batchSize;
                $params->firstResultIndex = $firstResultIndex;
                $params->options = [];

                $startTime = microtime(true);
                $result = $this->client->call('searchUnavailableTimeSlot', $params);
                $duration = microtime(true) - $startTime;

                if ($this->executionId) {
                    $this->praxedoApiLogger->logApiCall(
                        $this->executionId,
                        '/TimeSlotManager/searchUnavailableTimeSlot',
                        'POST',
                        json_encode($params),
                        json_encode($result),
                        200,
                        $duration
                    );
                }

                // Si pas de résultat ou pas d'entités, on arrête
                if (!$result || !isset($result->return) || !isset($result->return->entities)) {
                    break;
                }

                // Récupération des entités
                $entities = is_array($result->return->entities) 
                    ? $result->return->entities 
                    : [$result->return->entities];

                $allEntities = array_merge($allEntities, $entities);

                // Si le code n'est pas 200, c'est qu'on a tout récupéré
                if (!isset($result->return->resultCode) || $result->return->resultCode !== 200) {
                    break;
                }

                // On continue avec le lot suivant
                $firstResultIndex += $batchSize;

            } while (true);

            return $allEntities;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la récupération des créneaux', [
                'error' => $e->getMessage(),
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s'),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Formate une date Praxedo au format Wavesoft.
     *
     * @throws \DateMalformedStringException
     */
    private function formatPraxedoDate(string $date): string
    {
        $dateTime = new \DateTime($date);

        return $dateTime->format('d/m/Y H:i:s.v');
    }

    /**
     * Compare les créneaux entre Wavesoft et Praxedo pour identifier ceux à supprimer.
     */
    private function compareCreneaux(array $idsSurWS, array $idsSurPraxedo): string
    {
        $idsToDelete = '(';
        $first = true;

        foreach ($idsSurWS as $idW) {
            // On retire le préfixe CR_ pour la comparaison
            $idW = substr($idW, 3);
            $isFound = false;

            foreach ($idsSurPraxedo as $creneau) {
                $idP = (string) $creneau->id;
                if ($idW === $idP) {
                    $isFound = true;
                    break;
                }
            }

            if (!$isFound) {
                if (!$first) {
                    $idsToDelete .= ',';
                }

                $idsToDelete .= '\'CR_' . $idW . '\'';
                if ($first) {
                    $first = false;
                }
            }
        }

        return $idsToDelete . ')';
    }
}
