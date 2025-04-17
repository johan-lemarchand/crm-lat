<?php

namespace App\ODF\Infrastructure\Service;

use App\ODF\Domain\Service\TrimbleServiceInterface;
use App\Shared\Service\Timer;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;

class TrimbleService implements TrimbleServiceInterface
{
    private array $config;
    private ?string $accessToken = null;
    private Client $client;
    private string $apiUrl;
    private string $lockFilePath;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly LoggerInterface $logger,
        private readonly Timer $timer,
        private readonly OdfExecutionLogger $executionLogger,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir
    ) {
        $this->config = [
            'client_id' => $this->params->get('trimble.client_id'),
            'client_secret' => $this->params->get('trimble.client_secret'),
            'base_url' => $this->params->get('trimble.base_url'),
            'token_url' => $this->params->get('trimble.token_url'),
        ];

        if (!isset($this->config['client_id']) || !isset($this->config['client_secret']) || 
            !isset($this->config['base_url']) || !isset($this->config['token_url'])) {
            $this->logger->error('Configuration Trimble incomplète', [
                'config' => array_keys($this->config)
            ]);
            throw new Exception('Configuration Trimble incomplète. Vérifiez vos variables d\'environnement.');
        }

        $this->client = new Client();
        $this->apiUrl = $this->config['base_url'];
        $this->lockFilePath = $projectDir . '/var/locked_coupons.json';
        
        $this->logger->info('TrimbleService initialisé', [
            'apiUrl' => $this->apiUrl,
            'lockFilePath' => $this->lockFilePath
        ]);
    }

    /**
     * @throws Exception
     */
    public function createOrder(array $orderDetails): array
    {
        $usedCoupons = [];
        try {
            $this->resetAllCoupons();
            
            $this->executionLogger->addStep(
                'Début de création de commande Trimble',
                'info',
                $orderDetails
            );

            if ($this->accessToken === null) {
                $this->logger->info('Récupération du token d\'accès');
                $this->accessToken = $this->getAccessToken();
                $this->logger->info('Token d\'accès obtenu avec succès');
            }

            $orderData = [
                "ProcName" => "TAP_CREATE_ORDER_API",
                "ProcRequester" => "TAP",
                "request" => [
                    "customerPONumber" => $orderDetails[0]['PCDNUM'],
                    "currencyCode" => "EUR",
                    "activationCountry" => "FR",
                    "billToSiteUseId" => "999238",
                    "shipToSiteUseId" => "360574",
                    "billToContactEmail" => "d.chatel@latitudegps.com",
                    "billToContactPhone" => "DECLINE",
                    "shipToContactEmail" => "logistique@latitudegps.com",
                    "shipToContactPhone" => "DECLINE",
                    "lines" => []
                ]
            ];
            $lineNumber = 1;
            $lockedCoupons = $this->getLockedCoupons();
            foreach ($orderDetails as $line) {
                if ($line['PLDTYPE'] === 'C') {
                    $quantity = floatval($line['PLDQTE']);
                    
                    $availableCoupons = [];
                    $tempLockedCoupons = $lockedCoupons;
                    
                    for ($i = 0; $i < $quantity; $i++) {
                        $coupon = $this->getAvailableCoupons($line['PROCODE_PARENT'], $tempLockedCoupons);
                        if (empty($coupon)) {
                            throw new Exception("Pas assez de coupons trouvés pour l'ARTID: " . $line['ARTID']);
                        }
                        $availableCoupons[] = $coupon[0];
                    }
                    $serialNumber = $this->getSerialNumber($orderDetails, $line['PLDPEREID']);

                    if (!$serialNumber) {
                        throw new Exception("Pas de numéro de série trouvé pour le PROCODE: " . $line['PROCODE']);
                    }

                    for ($i = 0; $i < $quantity; $i++) {
                        if (empty($line['MFGMODELE'])) {
                            $this->logger->error('Erreur manufacturerModel manquant', [
                                'ligne' => $line,
                                'PROCODE' => $line['PROCODE'] ?? 'N/A',
                                'MFGMODELE' => $line['MFGMODELE'] ?? 'N/A'
                            ]);
                            throw new Exception("Le champ manufacturerModel (MFGMODELE) ne peut pas être vide pour le produit: " . $line['PROCODE']);
                        }
                        
                        $orderLine = [
                            "lineNumber" => $lineNumber++,
                            "serviceStartDate" => date('Y-m-d'),
                            "partNumber" => $line['PROCODE_PARENT'],
                            "serialNumber" => $line['PLDDIVERS'],
                            "serviceType" => "RTX",
                            "manufacturerModel" => $line['MFGMODELE'],
                            "autoRenewal" => "N",
                            "activationType" => "IMMEDIATE",
                            "activationDate" => date('Y-m-d')
                        ];
                        
                        $env = $this->params->get('kernel.environment');
                        if ($env !== 'dev') {
                            $orderLine["couponNumber"] = $availableCoupons[$i];
                        }
                        
                        $usedCoupons[] = $availableCoupons[$i];
                        $orderData['request']['lines'][] = $orderLine;
                    }

                    foreach ($availableCoupons as $coupon) {
                        $lockedCoupons[$coupon]['used'] = true;
                    }
                    
                    file_put_contents($this->lockFilePath, json_encode($lockedCoupons, JSON_PRETTY_PRINT));
                }
            }

            $this->executionLogger->logApiCall(
                'Trimble',
                'createOrder',
                [
                    'endpoint' => '/api/orders',
                    'method' => 'POST',
                    'orderDataRequest' => $orderData
                ],
                null
            );

            $response = $this->client->request('POST', $this->apiUrl . '/api/orders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => $orderData
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            $this->executionLogger->logApiCall(
                'Trimble',
                'createOrder',
                [
                    'endpoint' => '/api/orders',
                    'method' => 'POST'
                ],
                $result,
                null
            );

            return $result;

        } catch (RequestException | GuzzleException  $e) {
            $this->resetCouponsUsage($usedCoupons);
            $this->executionLogger->logApiCall(
                'Trimble',
                'createOrder',
                [
                    'endpoint' => '/api/orders',
                    'method' => 'POST'
                ],
                null,
                $e->getMessage()
            );
            
            // Récupérer le message d'erreur complet
            $errorMessage = $e->getMessage();
            $response = $e->getResponse();
            if ($response) {
                $errorBody = $response->getBody()->getContents();
                $this->logger->error('Erreur API Trimble complète', [
                    'message' => $errorMessage,
                    'body' => $errorBody
                ]);
                $errorMessage .= ' - Corps de la réponse: ' . $errorBody;
            }
            
            throw new Exception('Erreur API Trimble: ' . $errorMessage);
        }
    }

    private function getLockedCoupons(): array
    {
        if (file_exists($this->lockFilePath)) {
            return json_decode(file_get_contents($this->lockFilePath), true) ?? [];
        }
        return [];
    }

    private function getSerialNumber(array $orderDetails, int $parentId): ?string
    {
        foreach ($orderDetails as $detail) {
            if ($detail['PLDSTOTID'] === $parentId && !empty($detail['PLDDIVERS']) && $detail['PLDTYPE'] === 'L') {
                return $detail['PLDDIVERS'];
            }
        }
        return null;
    }

    /**
     * @throws Exception
     */
    private function getAvailableCoupons(string $proCodeParent, array &$lockedCoupons): array
    {
        $availableCoupons = [];
        if (!file_exists($this->lockFilePath)) {
            throw new Exception('Fichier de coupons non trouvé');
        }

        foreach ($lockedCoupons as $serial => $lock) {
            if ($lock['proCodeParent'] === $proCodeParent && (!isset($lock['used']) || !$lock['used'])) {
                $availableCoupons[] = $serial;

                $lockedCoupons[$serial]['used'] = true;

                break;
            }
        }

        if (!empty($availableCoupons)) {
            file_put_contents($this->lockFilePath, json_encode($lockedCoupons, JSON_PRETTY_PRINT));
        }

        return $availableCoupons;
    }

    private function resetCouponsUsage(array $coupons): void
    {
        if (empty($coupons)) {
            return;
        }

        if (!file_exists($this->lockFilePath)) {
            return;
        }

        $lockedCoupons = json_decode(file_get_contents($this->lockFilePath), true) ?? [];
        $updated = false;

        foreach ($coupons as $coupon) {
            if (isset($lockedCoupons[$coupon])) {
                $lockedCoupons[$coupon]['used'] = false;
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($this->lockFilePath, json_encode($lockedCoupons, JSON_PRETTY_PRINT));
        }
    }

    public function getActivation(string $numOrder): array
    {
        $requestData = [
            "ProcName" => "TAP_GET_ACTIVATION_STATUS_API",
            "ProcRequester" => "TAP",
            "request" => [
                "orderNumber" => $numOrder
            ]
        ];

        $this->logger->info('Début de vérification de l\'activation', [
            'endpoint' => '/api/activation',
            'method' => 'GET',
            'orderNumber' => $numOrder,
            'requestData' => $requestData
        ]);

        try {
            $result = $this->callApi($requestData);
            
            $this->logger->info('Fin de vérification d\'activation', [
                'endpoint' => '/api/activation',
                'method' => 'GET',
                'orderNumber' => $numOrder,
                'result' => $result
            ]);

            return $this->handleActivationResponse($result);
        } catch (Exception $e) {
            return $this->handleActivationException($e);
        }
    }

    /**
     * @throws Exception
     */
    public function getActivationBySerial(string $serialNumber): array
    {
        $requestData = [
            "ProcName" => "TAP_GET_ACTIVATION_STATUS_API",
            "ProcRequester" => "TAP",
            "request" => [
                "serialNumber" => $serialNumber
            ]
        ];

        $this->logger->info('Début de vérification de l\'activation par numéro de série', [
            'endpoint' => '/api/activation',
            'method' => 'GET',
            'serialNumber' => $serialNumber,
            'requestData' => $requestData
        ]);

        try {
            $result = $this->callApi($requestData);
            
            $this->logger->info('Fin de vérification d\'activation par numéro de série', [
                'endpoint' => '/api/activation',
                'method' => 'GET',
                'serialNumber' => $serialNumber,
                'result' => $result
            ]);
            
            return $result;
        } catch (Exception $e) {
            return $this->handleActivationException($e);
        }
    }

    /**
     * @throws Exception
     */
    public function getOrderBySerialNumber(string $serialNumber): array
    {
        $requestData = [
            "ProcName" => "TAP_GET_ORDER_API",
            "ProcRequester" => "TAP",
            "request" => [
                "serialNumber" => $serialNumber
            ]
        ];

        return $this->callApi($requestData);
    }

    /**
     * @throws Exception
     */
    public function getOrderByUniqueId(string $uniqueId): array
    {
        $requestData = [
            "ProcName" => "TAP_GET_ORDER_API",
            "ProcRequester" => "TAP",
            "request" => [
                "uniqueID" => $uniqueId
            ]
        ];

        return $this->callApi($requestData);
    }

    private function handleActivationResponse(array $result): array
    {
        if (isset($result['fault'])) {
            if (($result['fault']['status'] ?? '') === '504') {
                return [
                    'fault' => [
                        'status' => '504',
                        'message' => 'Gateway Timeout',
                        'description' => 'Gateway Timeout'
                    ]
                ];
            }
        }

        if (isset($result['statusCode']) && $result['statusCode'] !== '200') {
            $errorMessage = $this->formatErrorMessage($result);
            $this->logger->error('Erreur lors de la récupération de l\'activation', [
                'error' => $errorMessage,
                'result' => $result
            ]);

            return [
                'status' => 'error',
                'message' => $errorMessage,
                'details' => $result
            ];
        }

        if ($result['statusCode'] === '200' && $result['status'] === 'Success') {
            return $result;
        }

        return [
            'status' => 'error',
            'message' => "Réponse inattendue de l'API",
            'details' => $result
        ];
    }

    private function handleActivationException(Exception $e): array
    {
        if (str_contains($e->getMessage(), '504')) {
            return [
                'fault' => [
                    'status' => '504',
                    'message' => 'Gateway Timeout',
                    'description' => 'Gateway Timeout'
                ]
            ];
        }

        $errorMessage = sprintf(
            'Erreur lors de la récupération de l\'activation - Type: %s | Message: %s | Code: %s',
            get_class($e),
            $e->getMessage(),
            $e->getCode()
        );

        $this->logger->error('Exception lors de l\'activation', [
            'error' => $errorMessage,
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'status' => 'error',
            'message' => $errorMessage,
            'details' => [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]
        ];
    }

    /**
     * @throws Exception
     */
    private function callApi(array $requestData, string $method = 'GET'): array
    {
        if ($this->accessToken === null) {
            $this->accessToken = $this->getAccessToken();
        }

        $endpoint = $method === 'POST' ? $this->apiUrl : $this->apiUrl . '?request=' . urlencode(json_encode($requestData));
        
        $this->executionLogger->logApiCall(
            'Trimble',
            $requestData['ProcName'] ?? 'unknown',
            [
                'endpoint' => $endpoint,
                'method' => $method,
                'requestData' => $requestData
            ],
            null
        );

        try {
            if ($method === 'POST') {
                $response = $this->client->post($this->apiUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $requestData
                ]);
            } else {
                $response = $this->client->get($this->apiUrl . '?request=' . urlencode(json_encode($requestData)), [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Content-Type' => 'application/json'
                    ]
                ]);
            }

            $result = json_decode($response->getBody()->getContents(), true);
            
            $this->executionLogger->logApiCall(
                'Trimble',
                $requestData['ProcName'] ?? 'unknown',
                [
                    'endpoint' => $endpoint,
                    'method' => $method
                ],
                $result,
                null
            );

            return $result;

        } catch (GuzzleException $e) {
            $this->executionLogger->logApiCall(
                'Trimble',
                $requestData['ProcName'] ?? 'unknown',
                [
                    'endpoint' => $endpoint,
                    'method' => $method
                ],
                null,
                $e->getMessage()
            );
            
            // Récupérer le message d'erreur complet
            $errorMessage = $e->getMessage();
            if ($e instanceof RequestException && $e->getResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $this->logger->error('Erreur API Trimble complète', [
                    'message' => $errorMessage,
                    'body' => $errorBody
                ]);
                $errorMessage .= ' - Corps de la réponse: ' . $errorBody;
            }
            
            throw new Exception('Erreur API Trimble: ' . $errorMessage);
        }
    }

    private function formatErrorMessage(array $result): string
    {
        $errorDetails = [];
        
        if (isset($result['errorMessage'])) {
            $errorDetails[] = is_string($result['errorMessage']) 
                ? $result['errorMessage'] 
                : json_encode($result['errorMessage']);
        }
        
        if (isset($result['status'])) {
            $errorDetails[] = "Status: " . $result['status'];
        }
        
        if (isset($result['statusCode'])) {
            $errorDetails[] = "Code: " . $result['statusCode'];
        }

        if (isset($result['description'])) {
            $errorDetails[] = "Description: " . $result['description'];
        }

        return !empty($errorDetails) 
            ? "Erreur lors de la récupération de l'activation - " . implode(" | ", $errorDetails)
            : "Erreur lors de la récupération de l'activation - Détails non disponibles";
    }

    /**
     * @throws Exception
     */
    private function getAccessToken(): string
    {
        $this->executionLogger->logApiCall(
            'Trimble',
            'getAccessToken',
            [
                'endpoint' => $this->config['token_url'],
                'method' => 'POST'
            ],
            null
        );

        try {
            $response = $this->client->post($this->config['token_url'], [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'scope' => 'tapstoreapis'
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            $this->executionLogger->logApiCall(
                'Trimble',
                'getAccessToken',
                [
                    'endpoint' => $this->config['token_url'],
                    'method' => 'POST'
                ],
                ['status' => 'success', 'statusCode' => $response->getStatusCode()],
                null
            );

            if (!isset($result['access_token'])) {
                throw new Exception('Token d\'accès non trouvé dans la réponse');
            }

            return $result['access_token'];
        } catch (GuzzleException $e) {
            $this->executionLogger->logApiCall(
                'Trimble',
                'getAccessToken',
                [
                    'endpoint' => $this->config['token_url'],
                    'method' => 'POST'
                ],
                null,
                $e->getMessage()
            );
            throw new Exception('Erreur d\'authentification Trimble: ' . $e->getMessage());
        }
    }

    public function checkSerialNumber(string $serialNumber): array
    {
        $requestData = [
            'ProcName' => 'TAP_GET_ORDER_API',
            'ProcRequester' => 'TAP',
            'request' => [
                'serialNumber' => $serialNumber
            ]
        ];

        $this->logger->info('Début vérification numéro de série', [
            'endpoint' => '/api/order',
            'method' => 'GET',
            'serialNumber' => $serialNumber,
            'requestData' => $requestData
        ]);

        try {
            $result = $this->callApi($requestData);
            
            $this->logger->info('Fin vérification numéro de série', [
                'endpoint' => '/api/order',
                'method' => 'GET',
                'serialNumber' => $serialNumber,
                'result' => $result
            ]);

            if ($result['statusCode'] == '200' && $result['status'] == 'Success') {
                if (!empty($result['orderLine']) && is_array($result['orderLine'])) {
                    return [
                        'status' => 'success',
                        'orderLine' => $result
                    ];
                }
            }

            return [
                'status' => 'error',
                'orderLine' => null,
                'message' => "Numéro de série $serialNumber non trouvé dans le système Trimble"
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'orderLine' => null,
                'message' => $e->getMessage()
            ];
        }
    }

    private function resetAllCoupons(): void
    {
        if (!file_exists($this->lockFilePath)) {
            return;
        }

        $lockedCoupons = json_decode(file_get_contents($this->lockFilePath), true) ?? [];

        foreach ($lockedCoupons as $coupon => $data) {
            $lockedCoupons[$coupon]['used'] = false;
        }

        file_put_contents($this->lockFilePath, json_encode($lockedCoupons, JSON_PRETTY_PRINT));
    }
}
