<?php

namespace App\Applications\Praxedo\scripts\Clients\Service;

use App\Applications\Praxedo\Common\PraxedoClient;
use App\Applications\Praxedo\Common\PraxedoException;
use App\Service\PraxedoApiLogger;
use Doctrine\ORM\Exception\ORMException;
use Exception;
use Psr\Log\LoggerInterface;
use SoapFault;

class PraxedoClientsService
{
    private const MAX_BATCH_SIZE = 100;

    private PraxedoClient $customerClient;
    private PraxedoClient $locationClient;
    private ?string $executionId = null;

    /**
     * @throws SoapFault
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PraxedoApiLogger $praxedoApiLogger,
    ) {
        // Initialisation du client Customer Manager
        $this->customerClient = new PraxedoClient(
            sprintf('%s/CustomerManager?wsdl', $_ENV['PRAXEDO_BASE_URL']),
            $_ENV['PRAXEDO_LOGIN'],
            $_ENV['PRAXEDO_PASSWORD'],
            $this->logger,
            [
                'trace' => true,
                'exceptions' => true,
                'mtom' => true,
            ]
        );

        // Initialisation du client Location Manager
        $this->locationClient = new PraxedoClient(
            sprintf('%s/LocationManager?wsdl', $_ENV['PRAXEDO_BASE_URL']),
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

    /**
     * Définit l'identifiant d'exécution pour cette synchronisation
     */
    public function setExecutionId(string $executionId): void
    {
        $this->executionId = $executionId;
    }

    /**
     * Synchronise les clients depuis Wavesoft vers Praxedo
     */
    public function synchronizeClients(array $clientsWavesoft): array
    {
        $stats = [
            'total' => 0,
            'created_customers' => 0,
            'created_locations' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {
            if (empty($clientsWavesoft)) {
                $this->logger->info('Aucun client à synchroniser');
                return $stats;
            }

            $totalClients = count($clientsWavesoft);
            $stats['total'] = $totalClients;

            // Traitement par lots pour éviter les limitations API
            $batchSize = self::MAX_BATCH_SIZE;
            $totalBatches = ceil($totalClients / $batchSize);

            for ($batchIndex = 0; $batchIndex < $totalBatches; $batchIndex++) {
                $startIndex = $batchIndex * $batchSize;
                $endIndex = min($startIndex + $batchSize, $totalClients);
                $currentBatchSize = $endIndex - $startIndex;

                $this->logger->info('Traitement du lot de clients', [
                    'batch' => $batchIndex + 1,
                    'total_batches' => $totalBatches,
                    'batch_size' => $currentBatchSize
                ]);

                // Préparation des clients pour ce lot
                $customers = [];
                $locations = [];

                for ($i = 0; $i < $currentBatchSize; $i++) {
                    $clientData = $clientsWavesoft[$startIndex + $i];
                    
                    // Préparation des données client
                    $customer = $this->prepareCustomerData($clientData);
                    $customers[] = $customer;
                    
                    // Préparation des données d'emplacement si l'adresse est disponible
                    if (!empty($clientData['Adresse']) && !empty($clientData['Ville'])) {
                        $location = $this->prepareLocationData($clientData);
                        $locations[] = $location;
                    }
                }

                // Création des clients dans Praxedo
                $this->createCustomers($customers);
                $stats['created_customers'] += count($customers);

                // Création des emplacements dans Praxedo
                if (!empty($locations)) {
                    $this->createLocations($locations);
                    $stats['created_locations'] += count($locations);
                }
            }

            $this->logger->info('Synchronisation des clients terminée avec succès', [
                'total_clients' => $stats['total'],
                'created_customers' => $stats['created_customers'],
                'created_locations' => $stats['created_locations']
            ]);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la synchronisation des clients', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $stats['errors']++;
            $stats['error_details'][] = [
                'error' => $e->getMessage(),
                'batch' => $batchIndex ?? 'N/A'
            ];
            
            throw new PraxedoException('Erreur lors de la synchronisation des clients: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Prépare les données client pour Praxedo
     */
    private function prepareCustomerData(array $clientData): array
    {
        $customer = [
            'id' => $clientData['Code'],
            'name' => $clientData['Nom'],
            'city' => $clientData['Ville'] ?? '',
            'contact' => $clientData['Contact'] ?? '',
            'address' => $clientData['Adresse'] ?? '',
            'zipCode' => $clientData['CodePostal'] ?? '',
            'country' => $clientData['Pays'] ?? 'France',
        ];

        // Préparer les contacts
        $contacts = [];

        if (!empty($clientData['Portable'])) {
            $contacts[] = [
                'type' => 'MOBILE',
                'coordinates' => $clientData['Portable'],
                'flags' => 0,
                'label' => '',
                'extensions' => ['value' => '']
            ];
        }

        if (!empty($clientData['Email'])) {
            $contacts[] = [
                'type' => 'EMAIL',
                'coordinates' => $clientData['Email'],
                'flags' => 0,
                'label' => '',
                'extensions' => ['value' => '']
            ];
        }

        if (!empty($contacts)) {
            $customer['contacts'] = $contacts;
        }

        return $customer;
    }

    /**
     * Prépare les données d'emplacement pour Praxedo
     */
    private function prepareLocationData(array $clientData): array
    {
        $location = [
            'id' => $clientData['Code'] . '_A', // Suffixe A pour Adresse principale
            'customer' => $clientData['Code'],
            'name' => 'Adresse de livraison principale',
            'address' => $clientData['Adresse'],
            'city' => $clientData['Ville'],
            'zipCode' => $clientData['CodePostal'] ?? '',
            'country' => $clientData['Pays'] ?? 'France'
        ];

        // Ajouter les contacts si disponibles
        $contacts = [];
        
        if (!empty($clientData['Portable'])) {
            $contacts[] = [
                'type' => 'MOBILE',
                'flags' => 0,
                'coordinates' => $clientData['Portable']
            ];
        }
        
        if (!empty($clientData['Email'])) {
            $contacts[] = [
                'type' => 'EMAIL',
                'flags' => 0,
                'coordinates' => $clientData['Email']
            ];
        }
        
        if (!empty($contacts)) {
            $location['contacts'] = $contacts;
        }

        return $location;
    }

    /**
     * Crée un lot de clients dans Praxedo
     */
    private function createCustomers(array $customers): void
    {
        $startTime = microtime(true);

        try {
            $this->logger->info('Création de clients dans Praxedo', [
                'count' => count($customers)
            ]);

            // Conversion des données en format SOAP
            $params = new \stdClass();
            $params->customers = $customers;
            $params->options = [];
            // Appel API

            $result = $this->customerClient->call('createCustomers', $params);
            
            $duration = microtime(true) - $startTime;
            
            $this->logger->info('Clients créés avec succès dans Praxedo', [
                'duration' => round($duration, 2) . 's'
            ]);
            
            // Sérialiser le résultat de manière sécurisée
            $resultJson = 'Non-sérialisable';
            try {
                $resultJson = json_encode($result, JSON_PARTIAL_OUTPUT_ON_ERROR);
            } catch (\Exception $e) {
                $this->logger->warning('Impossible de sérialiser le résultat de l\'API', [
                    'error' => $e->getMessage()
                ]);
            }
            
            $this->praxedoApiLogger->logApiCall(
                $this->executionId,
                'createCustomers',
                'SUCCESS',
                json_encode(['count' => count($customers)]),
                $resultJson,
                200,
                $duration
            );
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->logger->error('Erreur lors de la création des clients dans Praxedo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $statusCode = 500;
            if ($e instanceof SoapFault) {
                $statusCode = $e->getCode() ?: 500;
            }
            
            $this->praxedoApiLogger->logApiCall(
                $this->executionId,
                'createCustomers',
                'ERROR',
                json_encode(['count' => count($customers)]),
                $e->getMessage(),
                $statusCode,
                $duration
            );
            
            throw new PraxedoException('Erreur lors de la création des clients dans Praxedo: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Crée un lot d'emplacements dans Praxedo
     */
    private function createLocations(array $locations): void
    {
        $startTime = microtime(true);

        try {
            $this->logger->info('Création d\'emplacements dans Praxedo', [
                'count' => count($locations)
            ]);

            // Conversion des données en format SOAP
            $params = new \stdClass();
            $params->locations = $locations;
            $params->options = [];


            // Aucune option pour cette version
            
            // Appel API
            $result = $this->locationClient->call('createLocations', $params);
            
            $duration = microtime(true) - $startTime;
            
            $this->logger->info('Emplacements créés avec succès dans Praxedo', [
                'duration' => round($duration, 2) . 's'
            ]);
            
            // Sérialiser le résultat de manière sécurisée
            $resultJson = 'Non-sérialisable';
            try {
                $resultJson = json_encode($result, JSON_PARTIAL_OUTPUT_ON_ERROR);
            } catch (\Exception $e) {
                $this->logger->warning('Impossible de sérialiser le résultat de l\'API', [
                    'error' => $e->getMessage()
                ]);
            }
            
            $this->praxedoApiLogger->logApiCall(
                $this->executionId,
                'createLocations',
                'SUCCESS',
                json_encode(['count' => count($locations)]),
                $resultJson,
                200,
                $duration
            );
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->logger->error('Erreur lors de la création des emplacements dans Praxedo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $statusCode = 500;
            if ($e instanceof SoapFault) {
                $statusCode = $e->getCode() ?: 500;
            }
            
            $this->praxedoApiLogger->logApiCall(
                $this->executionId,
                'createLocations',
                'ERROR',
                json_encode(['count' => count($locations)]),
                $e->getMessage(),
                $statusCode,
                $duration
            );
            
            throw new PraxedoException('Erreur lors de la création des emplacements dans Praxedo: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (ORMException $e) {

        }
    }
} 