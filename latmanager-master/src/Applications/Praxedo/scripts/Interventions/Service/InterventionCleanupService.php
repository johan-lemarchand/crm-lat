<?php

namespace App\Applications\Praxedo\scripts\Interventions\Service;

use App\Applications\Praxedo\Common\PraxedoClient;
use App\Entity\CommandExecution;
use App\Service\PraxedoApiLogger;
use App\Utils\SyncConstants;
use App\Utils\SyncDateManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;
use SoapFault;
use App\Applications\Wavesoft\WavesoftClient;

class InterventionCleanupService
{
    private ?CommandExecution $execution = null;
    private array $stats = [
        'total' => 0,
        'cancelled' => 0,
        'updated' => 0,
        'errors' => 0,
        'details' => []
    ];

    private PraxedoClient $businessEventClient;
    private PraxedoClient $businessEventAttachmentManager;

    /**
     * @throws SoapFault
     */
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly LoggerInterface $logger,
        private readonly PraxedoApiLogger $apiLogger,
        private readonly SyncDateManager $syncDateManager,
        private readonly WavesoftClient $wavesoftClient
    ) {
        try {
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

            $this->businessEventAttachmentManager = new PraxedoClient(
                sprintf('%s/businessEventAttachmentManager?wsdl', $_ENV['PRAXEDO_BASE_URL']),
                $_ENV['PRAXEDO_LOGIN'],
                $_ENV['PRAXEDO_PASSWORD'],
                $this->logger,
                [
                    'trace' => true,
                    'exceptions' => true,
                    'mtom' => true,
                ]
            );
        } catch (SoapFault $e) {
            $this->logger->error('Erreur lors de l\'initialisation du client Praxedo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function setExecution(CommandExecution $execution): void
    {
        $this->execution = $execution;
    }

    /**
     * @throws \DateMalformedStringException
     * @throws ORMException
     */
    public function cleanupInterventions(): array
    {
        try {
            $today = new \DateTime();
            $todayString = $today->format('Y-m-d');
            
            // Récupérer la date de dernière vérification
            $lastCheckDate = $this->syncDateManager->getLastSyncDate(SyncConstants::DISPARITION_INTER);
            ;
            if ($lastCheckDate !== $todayString) {
                // Calculer la date de début (60 jours avant)
                $startDate = new \DateTime((clone $today)->modify('-60 days')->format('Y-m-d'));
                // Récupérer les interventions à traiter
                $interventions = $this->getInterventionsToProcess($startDate, new \DateTime($todayString));

                foreach ($interventions as $intervention) {
                    $this->processIntervention($intervention);
                }
                
                // Mettre à jour la date de dernière vérification
                $this->syncDateManager->updateLastSyncDate(SyncConstants::DISPARITION_INTER);
            }
            
            return $this->stats;
        } catch (Exception | ORMException $e) {
            $this->logger->error('Erreur lors du nettoyage des interventions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * @throws ORMException
     * @throws Exception
     */
    private function getInterventionsToProcess(\DateTime $startDate, \DateTime $endDate): array
    {
        try {
            $startTime = microtime(true);
            $this->logger->info('Récupération des interventions', [
                'start' => $startDate,
                'end' => $endDate,
            ]);

            $businessEventsRequest = new \stdClass();
            $businessEventsRequest->request = new \stdClass();
            $businessEventsRequest->request->typeConstraint = 'MONT_TEST';
            $businessEventsRequest->request->statusConstraint = 'QUALIFIED';
            $businessEventsRequest->request->dateConstraints = [
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
                $params->request = $businessEventsRequest->request;
                $params->batchSize = $batchSize;
                $params->firstResultIndex = $firstResultIndex;
                $params->options = [];

                $result = $this->businessEventClient->call('searchEvents', $params);
                $duration = microtime(true) - $startTime;

                $this->apiLogger->logApiCall(
                    (string) $this->execution->getId(),
                    '/BusinessEventManager/searchEvents',
                    'POST',
                    json_encode($params),
                    json_encode($result, JSON_PRETTY_PRINT),
                    200,
                    $duration
                );

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

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des interventions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    private function processIntervention(\stdClass $intervention): void
    {
        try {
            $this->stats['total']++;
            
            // Vérifier si l'intervention existe dans Wavesoft
            $checkResult = $this->checkInterventionInWavesoft($intervention->id);
            if (empty($checkResult)) {
                // Annuler l'intervention dans Praxedo
                $this->cancelIntervention($intervention->id);
                $this->stats['cancelled']++;
            } else {
                // Mettre à jour l'intervention dans Praxedo
                $this->updateIntervention($intervention->id, $checkResult);
                $this->stats['updated']++;
            }
            
            $this->stats['details'][] = [
                'id' => $intervention->id,
                'status' => empty($checkResult) ? 'cancelled' : 'updated',
                'message' => empty($checkResult) ? 'Intervention annulée' : 'Intervention mise à jour'
            ];
        } catch (Exception $e) {
            $this->stats['errors']++;
            $this->stats['details'][] = [
                'id' => $intervention->id,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            $this->logger->error('Erreur lors du traitement de l\'intervention', [
                'intervention_id' => $intervention->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function checkInterventionInWavesoft(string $interventionId): array
    {
        $numericId = (int) $interventionId;
        
        $sql = "SELECT 
                    vlpvl.PCVNUM AS 'COC', 
                    vlpvl.PCVISSOLDE AS 'PV Soldé', 
                    suivi.PCVNUM_DST AS 'BL', 
                    vlpvl_suivi.PCVISSOLDE AS 'Suivi Soldé'  
                FROM V_LST_PIECEVENTES vlpvl  
                LEFT JOIN V_PIECEVENTE_SUIVI AS suivi ON suivi.PCVID_ORG = VLPVL.PCVID  
                LEFT JOIN V_LST_PIECEVENTES vlpvl_suivi ON vlpvl_suivi.PCVID = suivi.PCVID  
                WHERE vlpvl.PCVID = ?  
                AND ((suivi.PCVNUM_DST LIKE 'BLC%' AND vlpvl_suivi.PCVISSOLDE <> 'O') OR suivi.PCVNUM_DST IS NULL)";

        $stmt = $this->wavesoftClient->query($sql);
        $stmt->execute([$numericId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @throws ORMException
     * @throws Exception
     */
    private function cancelIntervention(string $interventionId): void
    {
        try {
            $startTime = microtime(true);
            
            $params = new \stdClass();
            $params->request = new \stdClass();
            $params->request->businessEvent = $interventionId;
            $params->request->cancellationReason = 'DEJA_FAIT';
            $params->request->cancellationMessage = 'Intervention déjà validée sur WaveSoft';
            
            $result = $this->businessEventClient->call('cancelEvent', $params);
            $duration = microtime(true) - $startTime;
            
            $this->apiLogger->logApiCall(
                (string) $this->execution->getId(),
                '/BusinessEventManager/cancelEvent',
                'POST',
                json_encode($params),
                json_encode($result, JSON_PRETTY_PRINT),
                200,
                $duration
            );
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'annulation de l\'intervention', [
                'intervention_id' => $interventionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @throws ORMException
     * @throws Exception
     */
    private function updateIntervention(string $interventionId, array $checkResult): void
    {
        try {
            $startTime = microtime(true);
            
            $params = new \stdClass();
            $params->request = new \stdClass();
            $params->request->entityId = $interventionId;
            $params->request->name = 'NCOM';
            $params->request->extensions = [
                (object) [
                    'key' => 'value',
                    'value' => $checkResult[0]['COC']
                ]
            ];
            
            $result = $this->businessEventAttachmentManager->call('createAttachment', $params);
            $duration = microtime(true) - $startTime;
            
            $this->apiLogger->logApiCall(
                (string) $this->execution->getId(),
                '/businessEventAttachmentManager/createAttachment',
                'POST',
                json_encode($params),
                json_encode($result, JSON_PRETTY_PRINT),
                200,
                $duration
            );
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour de l\'intervention', [
                'intervention_id' => $interventionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
} 