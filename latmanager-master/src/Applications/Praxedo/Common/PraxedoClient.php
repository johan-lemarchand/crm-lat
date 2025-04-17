<?php

namespace App\Applications\Praxedo\Common;

use KeepItSimple\Http\Soap\MTOMSoapClient;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class PraxedoClient
{
    /** @var SoapClient|MtomSoapClient|null */
    private SoapClient|null|MTOMSoapClient $soapClient = null;
    private static array $wsdlCache = [];
    private static ?FilesystemAdapter $cache = null;
    private const WSDL_CACHE_TTL = 2592000; // 30 jours
    private string $projectDir;
    private string $tmpDir;

    /**
     * @throws SoapFault
     */
    public function __construct(
        private readonly string $wsdlUrl,
        private readonly string $login,
        private readonly string $password,
        private readonly LoggerInterface $logger,
        private readonly array $options = [],
    ) {
        $this->projectDir = dirname(__DIR__, 4);
        $this->tmpDir = $this->projectDir . '/var/tmp';
        
        // Création du dossier var/tmp s'il n'existe pas
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0777, true);
        }

        if (!is_writable($this->tmpDir)) {
            throw new PraxedoException(sprintf('Le répertoire temporaire "%s" n\'est pas accessible en écriture', $this->tmpDir));
        }

        if (self::$cache === null) {
            $cacheDir = is_dir($this->projectDir . '/var/cache') ? $this->projectDir . '/var/cache' : $this->tmpDir;
            self::$cache = new FilesystemAdapter('praxedo_wsdl', 0, $cacheDir);
        }

        $this->logger->info('Démarrage initialisation client SOAP');

        $wsdlStartTime = microtime(true);
        $wsdl = $this->getCleanedWsdl();
        
        if (empty($wsdl)) {
            throw new PraxedoException('Le WSDL récupéré est vide');
        }
        
        $this->logger->info('WSDL récupéré et nettoyé', [
            'temps' => round(microtime(true) - $wsdlStartTime, 3) . 's',
            'taille' => strlen($wsdl) . ' octets'
        ]);
        
        $tempFile = tempnam($this->tmpDir, 'wsdl');
        if ($tempFile === false) {
            throw new PraxedoException('Impossible de créer le fichier temporaire pour le WSDL');
        }
        
        if (file_put_contents($tempFile, $wsdl) === false) {
            throw new PraxedoException('Impossible d\'écrire le WSDL dans le fichier temporaire');
        }

        $defaultOptions = [
            'trace' => 1,
            'exceptions' => true,
            'soap_version' => SOAP_1_1,
            'cache_wsdl' => WSDL_CACHE_MEMORY,
            'keep_alive' => true,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'encoding' => 'UTF-8',
            'login' => $this->login,
            'password' => $this->password,
            'stream_context' => stream_context_create([
                'http' => [
                    'header' => [
                        'Authorization: Basic ' . base64_encode($this->login . ':' . $this->password)
                    ]
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ])
        ];

        try {
            if (str_contains($this->wsdlUrl, 'BusinessEventManager') || ($this->options['mtom'] ?? false)) {
                $defaultOptions['soap_version'] = SOAP_1_2;
                $this->soapClient = new MTOMSoapClient($tempFile, $defaultOptions);
            } else {
                $this->soapClient = new SoapClient($tempFile, array_merge($defaultOptions, $this->options));
            }
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
        $this->logger->info('Client SOAP créé avec succès', [
            'endpoint' => str_replace('?wsdl', '', $this->wsdlUrl),
            'soap_version' => $defaultOptions['soap_version'],
            'client_type' => get_class($this->soapClient)
        ]);
    }

    private function getCleanedWsdl(): string
    {
        $cacheKey = md5($this->wsdlUrl . $this->login . $this->password);
        
        try {
            $cacheItem = self::$cache->getItem($cacheKey);
            
            if ($cacheItem->isHit()) {
                $wsdl = $cacheItem->get();
                if (!empty($wsdl)) {
                    $this->logger->debug('WSDL récupéré depuis le cache', [
                        'taille' => strlen($wsdl) . ' octets'
                    ]);
                    return $wsdl;
                }
                // Si le WSDL en cache est vide, on le récupère à nouveau
                $this->logger->warning('WSDL en cache est vide, nouvelle récupération');
            }

            $context = stream_context_create([
                'http' => [
                    'header' => [
                        'Authorization: Basic ' . base64_encode($this->login . ':' . $this->password),
                        'Accept: multipart/related,text/xml,application/soap+xml,application/dime,multipart/related,text/*',
                        'Connection: Keep-Alive'
                    ],
                    'timeout' => 30
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);

            $this->logger->debug('Tentative de récupération du WSDL', [
                'url' => $this->wsdlUrl
            ]);

            $wsdl = @file_get_contents($this->wsdlUrl, false, $context);

            if ($wsdl === false) {
                $error = error_get_last();
                throw new PraxedoException(sprintf(
                    'Impossible de récupérer le WSDL : %s',
                    $error ? $error['message'] : 'Erreur inconnue'
                ));
            }

            if (empty($wsdl)) {
                throw new PraxedoException('Le WSDL récupéré est vide');
            }

            $this->logger->debug('WSDL récupéré avec succès', [
                'taille' => strlen($wsdl) . ' octets'
            ]);

            // Nettoyage du WSDL
            $wsdl = preg_replace('/<wsp:Policy.*?<\/wsp:Policy>/s', '', $wsdl);
            $wsdl = preg_replace('/<wsp:PolicyReference.*?\/>/s', '', $wsdl);

            if (empty($wsdl)) {
                throw new PraxedoException('Le WSDL est vide après nettoyage');
            }

            $cacheItem->set($wsdl);
            $cacheItem->expiresAfter(self::WSDL_CACHE_TTL);
            self::$cache->save($cacheItem);

            return $wsdl;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du WSDL', [
                'url' => $this->wsdlUrl,
                'error' => $e->getMessage()
            ]);
            throw new PraxedoException(
                sprintf('Impossible de récupérer le WSDL : %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Erreur de cache WSDL', [
                'error' => $e->getMessage()
            ]);
            throw new PraxedoException(
                sprintf('Erreur de cache WSDL : %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    public function call(string $method, $parameters = null): mixed
    {
        try {
            $startTime = microtime(true);

            // Éviter les problèmes de conversion pour le logging
            $debugParams = null;
            try {
                $debugParams = json_encode($parameters, JSON_PARTIAL_OUTPUT_ON_ERROR);
            } catch (\Exception $e) {
                $debugParams = "Impossible de convertir les paramètres en JSON: " . $e->getMessage();
            }
            $this->logger->debug('Paramètres de la requête SOAP', [
                'method' => $method,
                'parameters' => $debugParams,
                'client_type' => get_class($this->soapClient),
                'is_mtom' => $this->soapClient instanceof MtomSoapClient
            ]);

            $result = $this->soapClient->__soapCall($method, [$parameters]);
            
            $duration = microtime(true) - $startTime;

            if ($duration > 1) {
                $this->logger->info('SOAP call duration warning', [
                    'method' => $method,
                    'duration' => round($duration, 2) . 's',
                ]);
            }

            $this->logger->debug('Réponse SOAP reçue', [
                'has_result' => $result !== null,
                'has_return' => isset($result->return),
                'response_headers' => $this->soapClient->__getLastResponseHeaders()
            ]);

            if ($result !== null && isset($result->return)) {
                return $result;
            }

            throw new PraxedoException('Format de réponse invalide ou non reconnu');
        } catch (\Exception $e) {
            $this->logger->error('SOAP Error', [
                'method' => $method,
                'error' => $e->getMessage(),
                'last_request' => $this->soapClient->__getLastRequest() ?? 'No request available',
                'last_response' => $this->soapClient->__getLastResponse() ?? 'No response available'
            ]);
            throw new PraxedoException(
                sprintf('Erreur lors de l\'appel à la méthode %s : %s', $method, $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Force le rafraîchissement du cache WSDL pour cet endpoint
     * @throws InvalidArgumentException
     */
    public function refreshWsdlCache(): void
    {
        $cacheKey = md5($this->wsdlUrl . $this->login . $this->password);
        self::$cache->delete($cacheKey);
        
        $this->getCleanedWsdl();
        
        $this->logger->info('Cache WSDL rafraîchi pour l\'endpoint', [
            'endpoint' => str_replace('?wsdl', '', $this->wsdlUrl)
        ]);
    }

    /**
     * Force le rafraîchissement du cache WSDL pour tous les endpoints
     */
    public static function clearAllWsdlCache(): void
    {
        self::$cache?->clear();
    }
    
    /**
     * Récupère la dernière requête SOAP envoyée
     */
    public function getLastRequest(): ?string
    {
        return $this->soapClient->__getLastRequest() ?? null;
    }
    
    /**
     * Récupère la dernière réponse SOAP reçue
     */
    public function getLastResponse(): ?string
    {
        return $this->soapClient->__getLastResponse() ?? null;
    }
}
