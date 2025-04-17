<?php

namespace App\Applications\Wavesoft\scripts\Currency\Service;

use App\Applications\Wavesoft\WavesoftClient;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\CommandExecution;

class CurrencyService
{
    private const ECB_API_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    private const MARGE_SUPERIEURE = 1.115;

    private ?CommandExecution $execution = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly WavesoftClient $wavesoftClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function setExecution(CommandExecution $execution): void
    {
        $this->execution = $execution;
    }

    public function synchronizeCurrencies(): array
    {
        if (!$this->execution) {
            throw new \RuntimeException('CommandExecution must be set before running synchronizeCurrencies');
        }

        $stats = [
            'total' => 0,
            'updated' => 0,
            'errors' => 0,
            'error_details' => [],
            'currencies' => []
        ];

        $query = "SELECT D.DEVID, D.DEVSYMBOLE 
                FROM DEVISES D
                WHERE D.DEVSYMBOLE != 'EUR'";
                
        $stmt = $this->wavesoftClient->getConnection()->prepare($query);
        $stmt->execute();
        $devises = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        try {
            $today = new DateTime();
            $today->modify('-1 day');

            foreach ($devises as $devise) {
                $currency = $devise['DEVSYMBOLE'];
                $deviseId = $devise['DEVID'];

                $query = "SELECT TOP 1 DVHDATEDEB as lastUpdate, DVHCOURSINV as lastTaux 
                         FROM DEVISES_HISTO DH
                         WHERE DH.DEVID = ?
                         ORDER BY DVHID DESC";
                
                $stmt = $this->wavesoftClient->getConnection()->prepare($query);
                $stmt->bindValue(1, $deviseId, \PDO::PARAM_INT);
                $stmt->execute();
                $lastData = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($lastData) {
                    $stats['currencies'][$currency] = [
                        'last_update' => $lastData['lastUpdate'],
                        'last_rate' => $lastData['lastTaux']
                    ];
                }
            }

            $xmlContent = file_get_contents(self::ECB_API_URL);
            if ($xmlContent === false) {
                throw new Exception('Impossible de récupérer les données de la BCE');
            }

            $xml = simplexml_load_string($xmlContent);

            $rates = [];
            foreach ($xml->Cube->Cube->Cube as $rate) {
                $currency = (string)$rate['currency'];
                $rates[$currency] = (float)$rate['rate'];
            }

            $rates['EUR'] = 1.0;
            $stats['total'] = count($rates);

            foreach ($rates as $currency => $rate) {
                try {
                    if (in_array($currency, array_column($devises, 'DEVSYMBOLE'))) {
                        $tauxConverti = $rate;
                        $taux = 1 / $tauxConverti;

                        $query = "SELECT DEVCOURSINV FROM DEVISES WHERE DEVSYMBOLE = ?";
                        $stmt = $this->wavesoftClient->getConnection()->prepare($query);
                        $stmt->bindValue(1, $currency, \PDO::PARAM_STR);
                        $stmt->execute();
                        $currentRate = $stmt->fetchColumn();

                        $currentRateFormatted = $currentRate;
                        if ($currentRate < 1) {
                            $currentRateFormatted = '0' . $currentRate;
                        }

                        if (isset($stats['currencies'][$currency])) {
                            $stats['currencies'][$currency]['new_rate'] = $taux;
                            $stats['currencies'][$currency]['last_rate'] = $currentRateFormatted;
                            $stats['currencies'][$currency]['bce_rate'] = $taux;
                            $stats['currencies'][$currency]['summary'] = [
                                'currency' => $currency,
                                'old_rate' => $currentRateFormatted,
                                'new_rate' => $taux,
                                'bce_rate' => $taux,
                                'last_update' => $stats['currencies'][$currency]['last_update'] ?? 'N/A',
                                'articles_updated' => count($articles ?? []),
                                'has_json_export' => true
                            ];
                        }

                        $query = "UPDATE DEVISES 
                                SET DEVCOURS = ?, 
                                    DEVCOURSTARIF = ?, 
                                    DEVCOURSINV = ? 
                                WHERE DEVSYMBOLE = ?";
                        
                        $stmt = $this->wavesoftClient->getConnection()->prepare($query);
                        $stmt->bindValue(1, $tauxConverti, \PDO::PARAM_STR);
                        $stmt->bindValue(2, $tauxConverti, \PDO::PARAM_STR);
                        $stmt->bindValue(3, $taux, \PDO::PARAM_STR);
                        $stmt->bindValue(4, $currency, \PDO::PARAM_STR);

                        $stmt->execute();

                        $query = "SELECT TOP 1 DVHID, DEVID, DVHDATEDEB, DVHDATEFIN, DVHCOURS
                                FROM DEVISES_HISTO 
                                WHERE DEVID = ? 
                                ORDER BY DVHID DESC";
                        
                        $stmt = $this->wavesoftClient->getConnection()->prepare($query);
                        $stmt->bindValue(1, $devises[array_search($currency, array_column($devises, 'DEVSYMBOLE'))]['DEVID'], \PDO::PARAM_INT);
                        $stmt->execute();
                        $lastEntry = $stmt->fetch(\PDO::FETCH_ASSOC);
                        
                        $yesterdayRate = $lastEntry['DVHCOURS'];
                        if ($yesterdayRate < 1) {
                            $yesterdayRate = '0' . $yesterdayRate;
                        }
                        if ($lastEntry) {
                            $lastDate = new DateTime($lastEntry['DVHDATEDEB']);
                            if ($today->format('Y-m-d') === $lastDate->format('Y-m-d')) {
                                $query = "UPDATE DEVISES_HISTO 
                                        SET DVHCOURS = ?, 
                                            DVHCOURSINV = ?, 
                                            DATEUPDATE = ? 
                                        WHERE DVHID = ?";
                                
                                $stmt = $this->wavesoftClient->getConnection()->prepare($query);
                                $stmt->bindValue(1, $taux, \PDO::PARAM_STR);
                                $stmt->bindValue(2, $tauxConverti, \PDO::PARAM_STR);
                                $stmt->bindValue(3, $today->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                                $stmt->bindValue(4, $lastEntry['DVHID'], \PDO::PARAM_INT);
                                $stmt->execute();
                            } else {
                                $query = "UPDATE DEVISES_HISTO 
                                        SET DATEUPDATE = CAST(? AS DATETIME2), 
                                            DVHDATEFIN = CAST(? AS DATETIME2)
                                        WHERE DVHID = ?";

                                $stmt = $this->wavesoftClient->getConnection()->prepare($query);
                                $stmt->bindValue(1, $today->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                                $stmt->bindValue(2, $today->format('Y-m-d'), \PDO::PARAM_STR);
                                $stmt->bindValue(3, $lastEntry['DVHID'], \PDO::PARAM_INT);
                                $stmt->execute();
                                $deviseId = $devises[array_search($currency, array_column($devises, 'DEVSYMBOLE'))]['DEVID'];
                                $this->insertHistoryEntry($today, $taux, $tauxConverti, $deviseId);
                            }
                        } else {
                            $deviseId = $devises[array_search($currency, array_column($devises, 'DEVSYMBOLE'))]['DEVID'];
                            $this->insertHistoryEntry($today, $taux, $tauxConverti, $deviseId);
                        }

                        $this->updateSalesCoefficients($taux, $today, $currency);
                        $this->exportCurrenciesToJson($currency);

                        $stats['updated']++;
                        $this->logger->info("Devise mise à jour", [
                            'devise' => $currency,
                            'ancien_taux' => $yesterdayRate ?? 'N/A',
                            'nouveau_taux' => $taux,
                            'derniere_maj' => $stats['currencies'][$currency]['last_update'] ?? 'N/A',
                            'articles_mis_a_jour' => count($articles ?? [])
                        ]);
                    }
                } catch (Exception $e) {
                    $stats['errors']++;
                    $stats['error_details'][] = [
                        'currency' => $currency,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ];
                    $this->logger->error("Erreur lors de la mise à jour de la devise", [
                        'currency' => $currency,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

        } catch (TransportExceptionInterface $e) {
            $stats['errors']++;
            $stats['error_details'][] = [
                'error' => 'Erreur de connexion à l\'API BCE: ' . $e->getMessage()
            ];
            $this->logger->error("Erreur de connexion à l'API BCE", [
                'error' => $e->getMessage()
            ]);
        } catch (Exception $e) {
            $stats['errors']++;
            $stats['error_details'][] = [
                'error' => $e->getMessage()
            ];
            $this->logger->error("Erreur lors de la récupération des taux de change", [
                'error' => $e->getMessage()
            ]);
        }

        return $stats;
    }

    /**
     * @throws Exception
     */
    private function updateSalesCoefficients(float $taux, DateTime $today, string $currency): void
    {
        try {
            $newCoefVenteGENERAL = (1 / $taux) * self::MARGE_SUPERIEURE;
            $newCoefVenteAFRIQUE = $newCoefVenteGENERAL + 0.29;
            $jourSemaine = (int)$today->format('w');

            $query = "SELECT a.ARTID
                     FROM ARTICLES a
                     LEFT JOIN PRODUITS p ON p.ARTID = a.ARTID
                     LEFT JOIN TIERS four ON four.TIRID = p.TIRID
                     LEFT JOIN DEVISES d ON d.DEVID = p.DEVID 
                     WHERE a.ARTISACTIF = 'O' 
                     AND d.DEVSYMBOLE = ?
                     AND p.PROISPRINCIPAL = 'O' AND four.TIRCODE = 'PPLANTI'";
            
            $stmt = $this->wavesoftClient->getConnection()->prepare($query);
            $stmt->bindValue(1, $currency, \PDO::PARAM_STR);
            $stmt->execute();
            $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $modifiedArticles = [];

            foreach ($articles as $article) {
                // Mise à jour tarif GENERAL
                $query = "UPDATE ARTTARIF
                         SET ATFCALCCOEFF = :coef" .
                         ($jourSemaine === 2 ? ", ATFISAUTO = 'O'" : "") .
                         " FROM ARTICLES A
                         LEFT JOIN ARTTARIF AT ON AT.ARTID = A.ARTID
                         LEFT JOIN TARIFS T ON T.TRFID = AT.TRFID
                         WHERE T.TRFCODE = 'GENERAL'
                         AND A.ARTID = :artid";
                
                $stmt = $this->wavesoftClient->query($query);
                $stmt->bindValue(':coef', $newCoefVenteGENERAL, \PDO::PARAM_STR);
                $stmt->bindValue(':artid', $article['ARTID'], \PDO::PARAM_INT);
                $stmt->execute();

                // Mise à jour tarif AFRIQUE
                $query = "UPDATE ARTTARIF
                         SET ATFCALCCOEFF = :coef" .
                         ($jourSemaine === 2 ? ", ATFISAUTO = 'O'" : "") .
                         " FROM ARTICLES A
                         LEFT JOIN ARTTARIF AT ON AT.ARTID = A.ARTID
                         LEFT JOIN TARIFS T ON T.TRFID = AT.TRFID
                         WHERE T.TRFCODE = 'AFRIQUE'
                         AND A.ARTID = :artid";
                
                $stmt = $this->wavesoftClient->query($query);
                $stmt->bindValue(':coef', $newCoefVenteAFRIQUE, \PDO::PARAM_STR);
                $stmt->bindValue(':artid', $article['ARTID'], \PDO::PARAM_INT);
                $stmt->execute();

                $modifiedArticles[] = [
                    'article_id' => $article['ARTID'],
                    'nouveau_coef_general' => $newCoefVenteGENERAL,
                    'nouveau_coef_afrique' => $newCoefVenteAFRIQUE
                ];
            }

            if (!empty($modifiedArticles)) {
                $csvDirectory = __DIR__ . '/../../../../../../var/currency';
                if (!is_dir($csvDirectory)) {
                    mkdir($csvDirectory, 0777, true);
                }

                $articleIds = array_column($modifiedArticles, 'article_id');
                $articleCodes = [];
                if (!empty($articleIds)) {
                    $query = "SELECT ARTID, ARTCODE FROM ARTICLES WHERE ARTID IN (" . implode(',', $articleIds) . ")";
                    $stmt = $this->wavesoftClient->getConnection()->query($query);
                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $articleCodes[$row['ARTID']] = $row['ARTCODE'];
                    }
                }

                $filename = sprintf(
                    '%s/coefficients_update_%s_%s.csv',
                    $csvDirectory,
                    $currency,
                    $today->format('Y-m-d')
                );

                $handle = fopen($filename, 'w');
                
                fputcsv($handle, [
                    'Article ID',
                    'Code Article',
                    'Devise',
                    'Date',
                    'Nouveau Coef General',
                    'Nouveau Coef Afrique'
                ]);

                foreach ($modifiedArticles as $article) {
                    fputcsv($handle, [
                        $article['article_id'],
                        $articleCodes[$article['article_id']] ?? '',
                        $currency,
                        $today->format('Y-m-d'),
                        $newCoefVenteGENERAL,
                        $newCoefVenteAFRIQUE
                    ]);
                }

                fclose($handle);

                $this->logger->info("Fichier CSV créé", [
                    'fichier' => $filename,
                    'nombre_articles' => count($modifiedArticles)
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error("Erreur lors de la mise à jour des coefficients de vente", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    private function exportCurrenciesToJson(string $currency): void
    {
        try {
            $query = "SELECT DH.DVHID, 
                            format(DH.DVHDATEDEB, 'MM/dd/yyyy') as DateDeb, 
                            format(DH.DVHDATEFIN, 'MM/dd/yyyy') as DateFin, 
                            DVHCOURSINV
                     FROM DEVISES_HISTO DH
                     LEFT JOIN DEVISES D ON D.DEVID = DH.DEVID
                     WHERE D.DEVSYMBOLE = ?
                     AND YEAR(DH.DVHDATEDEB) >= YEAR(GETDATE()) - 1
                     ORDER BY DH.DVHDATEDEB";

            $stmt = $this->wavesoftClient->getConnection()->prepare($query);
            $stmt->bindValue(1, $currency, \PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $jsonData = [];
            foreach ($data as $row) {
                $taux = $row['DVHCOURSINV'];
                if ($taux < 1) {
                    $taux = "0" . $taux;
                }

                $jsonData[] = sprintf(
                    '"%s":{"DateDeb":"%s","DateFin":"%s","Taux":%s}',
                    $row['DVHID'],
                    $row['DateDeb'],
                    $row['DateFin'],
                    number_format((float)$taux, 12)
                );
            }

            $jsonContent = "{\n" . implode(",\n", $jsonData) . "\n}";

            if (empty($jsonContent)) {
                throw new Exception("Aucune donnée à exporter");
            }

            $networkPath = '\\\\192.168.19.10\\ftp\\Netcash\\Source';
            if (!is_dir($networkPath)) {
                throw new Exception("Le dossier réseau n'est pas accessible: $networkPath");
            }

            $filePath = $networkPath . '\\devise_' . $currency . '.json';
            
            $result = @file_put_contents($filePath, $jsonContent);
            if ($result === false) {
                throw new Exception(sprintf(
                    "Impossible d'écrire le fichier d'export des devises pour %s. Erreur: %s", 
                    $currency,
                    error_get_last()['message'] ?? 'Erreur inconnue'
                ));
            }
        } catch (Exception $e) {
            $this->logger->error("Erreur lors de l'export du fichier JSON des devises", [
                'devise' => $currency,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Insère une nouvelle entrée dans l'historique des devises
     */
    private function insertHistoryEntry(DateTime $date, float $taux, float $tauxConverti, int $deviseId): void
    {
        $query = "INSERT INTO DEVISES_HISTO 
                (DEVID, DVHDATEDEB, DVHDATEFIN, DVHCOURS, USRMODIF, DATEUPDATE, DATECREATE, DVHCOURSINV)
                VALUES 
                (?, CAST(? AS DATETIME2), '2999-01-01 00:00:00.000', ?, 
                'SCRIPT_CURRENCY', CAST(? AS DATETIME2), CAST(? AS DATETIME2), ?)";
        
        $stmt = $this->wavesoftClient->getConnection()->prepare($query);
        $stmt->bindValue(1, $deviseId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $date->format('Y-m-d'), \PDO::PARAM_STR);
        $stmt->bindValue(3, $taux, \PDO::PARAM_STR);
        $stmt->bindValue(4, $date->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->bindValue(5, $date->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->bindValue(6, $tauxConverti, \PDO::PARAM_STR);
        $stmt->execute();
    }
} 