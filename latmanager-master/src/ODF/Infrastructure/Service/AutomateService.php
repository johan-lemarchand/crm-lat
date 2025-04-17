<?php

namespace App\ODF\Infrastructure\Service;

use App\Entity\WavesoftLog;
use App\ODF\Domain\Repository\AutomateRepositoryInterface;
use App\ODF\Domain\Repository\MemoRepositoryInterface;
use App\ODF\Domain\Service\AutomateServiceInterface;
use App\Repository\WavesoftLogRepository;
use Doctrine\DBAL\Connection;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class AutomateService implements AutomateServiceInterface
{
    private const AUTOMATE_ENTITY_PIECEVENTE = 99;
    private const AUTOMATE_ENTITY_PIECESTOCK = 299;

    private const MAX_ATTEMPTS = 80;
    private const TIMEOUT = 1;

    public function __construct(
        private AutomateRepositoryInterface              $automateRepository,
        #[Autowire('%tcp.host%')] private string         $host,
        #[Autowire('%tcp.port%')] private int            $port,
        #[Autowire('%tcp.instance_sql%')] private string $instanceSql,
        #[Autowire('%tcp.dossier%')] private string      $dossier,
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection                               $connection,
        private TcpService                               $tcpService,
        private LoggerInterface                          $logger,
        private MemoRepositoryInterface                  $memoRepository,
        private WavesoftLogRepository                    $wavesoftLogRepository,
    ) {}

    /**
     * Traite la fabrication via l'automate
     *
     * @param array $items Articles et coupons
     * @param string $codeAffaire Code affaire
     * @param string $orderNumber Numéro de commande
     * @param string $user Utilisateur
     * @param string|null $memo Texte du mémo
     * @return bool Succès ou échec
     * @throws Exception
     */
    public function processFabricationAutomate(array $items, string $codeAffaire, string $orderNumber, string $user, ?string $memo = null): bool
    {
        try {
            $this->logger->info('Début du processus de fabrication', [
                'orderNumber' => $orderNumber,
                'user' => $user
            ]);

            $formatMemo = $memo ? str_replace(['"', ';'], ["'", ":"], $memo) : '';
            $fileContent = $this->buildFabricationFileContent($items, $codeAffaire, $orderNumber, $user, $formatMemo);

            $trsid = $this->executeAutomateTask($fileContent, self::AUTOMATE_ENTITY_PIECESTOCK);
            $this->tcpService->sendWaveSoftCommand($trsid);

            return $this->waitForCompletion($trsid);

        } catch (Exception $e) {
            $this->logger->error('Erreur lors du processus de fabrication', [
                'error' => $e->getMessage(),
                'items' => $items,
                'codeAffaire' => $codeAffaire,
                'orderNumber' => $orderNumber,
                'user' => $user
            ]);
            throw $e;
        }
    }

    public function processDeleteAutomate(string $pcdnum): array
    {
        $messages = [];
        try {
            $this->logger->info('Tentative de suppression du BTR', [
                'pcdnum' => $pcdnum
            ]);

            $fileContent = "SUP;$pcdnum;";
            $trsid = $this->executeAutomateTask($fileContent, self::AUTOMATE_ENTITY_PIECESTOCK);
            $this->tcpService->sendWaveSoftCommand((string)$trsid);

            if ($this->waitForCompletion($trsid)) {
                $this->logger->info('Suppression réussie', [
                    'pcdnum' => $pcdnum
                ]);

                $messages[] = [
                    'title' => 'Suppression de la pièce divers',
                    'content' => "La pièce divers a été supprimée avec succès",
                    'status' => 'success'
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Échec de la suppression', [
                'error' => $e->getMessage(),
                'pcdnum' => $pcdnum
            ]);

            $messages[] = [
                'title' => 'Erreur suppression',
                'content' => $e->getMessage(),
                'status' => 'error',
                'showMailto' => str_contains($e->getMessage(), 'informatique@latitudegps.com')
            ];
        }
        return $messages;
    }

    /**
     * Clôture un ODF via l'automate
     *
     * @param string $odfNumber Numéro de l'ODF
     * @return bool Succès ou échec
     * @throws Exception
     */
    public function processCloseODFAutomate(string $odfNumber): bool
    {
        try {
            $fileContent = "CLO;$odfNumber;";
            $trsid = $this->executeAutomateTask($fileContent, self::AUTOMATE_ENTITY_PIECESTOCK);
            $this->tcpService->sendWaveSoftCommand((string)$trsid);

            return $this->waitForCompletion($trsid);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la clôture de l\'ODF', [
                'error' => $e->getMessage(),
                'odfNumber' => $odfNumber
            ]);
            throw $e;
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    private function executeAutomateTask(string $fileContent, int $entityId): int
    {
        $sql = "DECLARE @return_value int;
                DECLARE @trsid int;

                EXEC @return_value = ws_sp_add_tache_automate
                    @TRSENTITE = :trsentite,
                    @TRSPROFIL = null,
                    @TRSFILE = :trsfile,
                    @TRSSEPARATEUR = 'V',
                    @TRSISTCP = 'O';

                SELECT TOP 1 @trsid = TRSID 
                FROM WSAUTOMATE 
                ORDER BY DATECREATE DESC;

                SELECT @trsid as new_trsid;";

        $result = $this->connection->executeQuery($sql, [
            'trsentite' => $entityId,
            'trsfile' => $fileContent
        ])->fetchAssociative();

        if (!$result || !isset($result['new_trsid'])) {
            throw new Exception('Impossible de créer la tâche automate');
        }

        return (int)$result['new_trsid'];
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    private function waitForCompletion(int $trsid): bool
    {
        $attempts = 0;
        while ($attempts < self::MAX_ATTEMPTS) {
            $status = $this->checkStatus($trsid);

            if ($status === 'T') {
                return true;
            }
            if ($status === 'E') {
                $errorMessage = $this->formatMailtoMessage($trsid);
                throw new Exception($errorMessage);
            }

            sleep(self::TIMEOUT);
            $attempts++;
        }

        throw new Exception("Le traitement automatique n'a pas abouti après " . self::MAX_ATTEMPTS . " tentatives (trsid: $trsid)");
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function checkStatus(int $trsid): ?string
    {
        $result = $this->connection->executeQuery(
            "SELECT TRSETAT FROM WSAUTOMATE WHERE TRSID = :trsid",
            ['trsid' => $trsid]
        )->fetchOne();

        return $result ?: null;
    }

    /**
     * Supprime les verrous dans WSLOCK
     *
     * @param string $pcdnum Numéro de pièce
     */
    public function deleteWslCodeInWSLOCK(string $pcdnum): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM WSLOCK WHERE WSLCODE = :pcdnum',
                ['pcdnum' => $pcdnum]
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression des verrous', [
                'error' => $e->getMessage(),
                'pcdnum' => $pcdnum
            ]);
        }
    }

    public function processTransfertAutomate(array $items, array $codeAffaire): array
    {
        $messages = [];
        try {
            $pcdnum = $items['articles'][0]['PCDNUM'];
            $artCode = $items['articles'][0]['ARTCODE'];
            $btrNumber = "BTR" . $pcdnum;

            $fileContent = $this->buildTransfertFileContent($btrNumber, $pcdnum, $codeAffaire, $items);

            $trsid = $this->executeAutomateTask($fileContent, self::AUTOMATE_ENTITY_PIECESTOCK);
            $this->tcpService->sendWaveSoftCommand($trsid);

            if ($this->waitForCompletion($trsid)) {
                $messages[] = [
                    'title' => 'Réservation de coupon',
                    'content' => "Réservation de coupon effectuée avec succès pour l'article {$artCode}. ID-automate: $trsid",
                    'status' => 'success'
                ];
            }
        } catch (Exception $e) {
            $messages[] = [
                'title' => 'Erreur transfert',
                'content' => $e->getMessage(),
                'status' => 'error',
                'showMailto' => str_contains($e->getMessage(), 'informatique@latitudegps.com')
            ];
        }
        return $messages;
    }

    /**
     * @throws Exception
     */
    private function buildTransfertFileContent(string $btrNumber, string $pcdnum, array $codeAffaire, array $items): string
    {
        if (empty($items['articles'])) {
            throw new Exception("Aucun article trouvé dans les données");
        }

        $fileContent = "E;BTRSTK;" .
            date('d/m/Y') . ";" .
            date('d/m/Y') .
            ";BTR;" .
            $btrNumber . ";" .
            "Bon de transfert de " . $pcdnum . ";" .
            $codeAffaire['affaire']['code'] . ";" .
            "GENERAL;APITRIMBLE;LATITUDEGPS;\n";

        $fileContent .= "ED;REFERENT;;DATERETOUR;;COLISAGE;1;STATUTAPI;;DATEAPI;;USERAPI;;\n";

        $hasProcessedCoupons = false;

        if (!empty($items['coupons'])) {
            foreach ($items['coupons'] as $coupon) {
                if (isset($coupon['ARTCODE'])) {
                    $fileContent .= $this->buildTransfertLine($coupon, $coupon['ARTCODE'], $codeAffaire['affaire']['code']);
                    $hasProcessedCoupons = true;
                }
            }
        }

        if (!$hasProcessedCoupons) {
            throw new Exception("Aucun coupon trouvé dans les articles");
        }

        return $fileContent;
    }

    private function buildTransfertLine(array $item, string $artCode, string $affCode): string
    {
        return "LA;" . $artCode . ";1;;" .
            str_replace('.', ',', $item['OPECUMP']) . ";;" .
            str_replace('.', ',', $item['OPECUMP']) . ";;;;" .
            $item['OPENUMSERIE'] . ";" . $affCode . ";;;;;;;" .
            str_replace('.', ',', $item['OPECUMP']) . ";\n";
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function formatMailtoMessage(string $id): string
    {
        $error = $this->connection->executeQuery(
            "SELECT TRSERREUR FROM WSAUTOMATE WHERE TRSID = :trsid",
            ['trsid' => $id]
        )->fetchOne();

        // Formater le message d'erreur pour une meilleure lisibilité
        return sprintf(
            "Une erreur est survenue lors du traitement automatique\n\nID: %s\nStatut: E\nErreur: %s\n\nPour plus d'informations, contacter le service informatique à l'adresse suivante : informatique@latitudegps.com",
            $id,
            $error
        );
    }

    /**
     * Construit le contenu du fichier de fabrication
     *
     * @param array $items Articles et coupons
     * @param string $codeAffaire Code affaire
     * @param string $orderNumber Numéro de commande
     * @param string $user Utilisateur
     * @param string $formatMemo Mémo formaté
     * @return string Contenu du fichier
     */
    private function buildFabricationFileContent(array $items, string $codeAffaire, string $orderNumber, string $user, string $formatMemo): string
    {
        $pcdnum = $items['articles'][0]['PCDNUM'] ?? '';
        $fileContent = "E;BDFSTK;" . date('d/m/Y') . ";" . date('d/m/Y') . ";BDFA;BDF" .
            $pcdnum . ";Order Trimble : " . $orderNumber . ";" . $codeAffaire . ";APITRIMBLE;GENERAL;LATITUDEGPS;\n";

        $fileContent .= "ED;REFERENT;;DATERETOUR;;COLISAGE;1;STATUTAPI;Succès;DATEAPI;" .
            date('d/m/Y H:i:s') . ";USERAPI;" . $user . ";\n";
        foreach ($items['articles'] as $article) {
            if ($article['PLDTYPE'] === 'L') {
                $numCoupons = (int)$article['PLDQTE'];
                $coupons = $article['coupons'] ?? [];
                
                // Générer une ligne par année d'abonnement
                for ($i = 0; $i < $numCoupons; $i++) {
                    // Créer l'article
                    $articleLine = $this->buildFabricationArticleLine(
                        $article, 
                        $codeAffaire, 
                        $i + 1, 
                        $numCoupons
                    );
                    
                    $fileContent .= $articleLine;
                    
                    // Ajouter les détails du coupon s'il existe
                    if (!empty($coupons)) {
                        $couponIndex = min($i, count($coupons) - 1);
                        $coupon = $coupons[$couponIndex];

                        $detailLine = $this->buildFabricationDetailLine($coupon);
                        $fileContent .= $detailLine;
                        $pieceLine = $this->buildFabricationPieceLine($coupon, $codeAffaire);
                        $fileContent .= $pieceLine;
                    }
                }
            }
        }
        $fileContent .= "NO;\"". $formatMemo ."\";";
        return $fileContent;
    }

    /**
     * Construit une ligne d'article pour le fichier de fabrication
     *
     * @param array $article Article
     * @param string $codeAffaire Code affaire
     * @param int $index Index de l'article
     * @param int $totalCoupons Nombre total de coupons
     * @return string Ligne d'article
     */
    private function buildFabricationArticleLine(array $article, string $codeAffaire, int $index, int $totalCoupons): string
    {
        $plddivers = $article['PLDDIVERS'] ?? '';
        
        if (!empty($article['coupons']) && isset($article['coupons'][$index-1])) {
            $coupon = $article['coupons'][$index-1];
            if (isset($coupon['serviceStartDate']) && isset($coupon['serviceEndDate'])) {
                $startYear = substr($coupon['serviceStartDate'], 0, 4);
                $endYear = substr($coupon['serviceEndDate'], 0, 4);
                $plddivers .= " / " . $startYear . $endYear;
            }
        }

        return "LA;" .
            ($article['ARTCODE'] ?? '') . ";1;;" .
            str_replace('.', ',', $article['ARTCUMP'] ?? '0') . ";;" .
            str_replace('.', ',', $article['ARTCUMP'] ?? '0') . ";;;;" .
            $plddivers . ";" .
            $codeAffaire . ";;;;;;;;" .
            str_replace('.', ',', $article['ARTCUMP'] ?? '0') . ";\n";
    }

    /**
     * Construit une ligne de détail pour le fichier de fabrication
     *
     * @param array $coupon Coupon
     * @return string Ligne de détail
     */
    private function buildFabricationDetailLine(array $coupon): string
    {
        return "LD;" .
            "QRCODE;" . ($coupon['qrcode'] ?? 'N') . ";" .
            "CODESN;" . ($coupon['passcode'] ?? '') . ";" .
            "DATEDEBUT;" . ($coupon['serviceStartDate'] ?? '') . ";" .
            "DATEFIN;" . ($coupon['serviceEndDate'] ?? '') . ";\n";
    }

    /**
     * Construit une ligne de pièce pour le fichier de fabrication
     *
     * @param array $coupon Coupon
     * @param string $codeAffaire Code affaire
     * @return string Ligne de pièce
     */
    private function buildFabricationPieceLine(array $coupon, string $codeAffaire): string
    {
        return "LP;" .
            ($coupon['ARTCODE'] ?? '') . ";1;;" .
            str_replace('.', ',', $coupon['OPECUMP'] ?? '0') . ";;" .
            str_replace('.', ',', $coupon['OPECUMP'] ?? '0') . ";;;;" .
            ($coupon['OPENUMSERIE'] ?? '') . ";" .
            $codeAffaire . ";;;;;;;;" .
            str_replace('.', ',', $coupon['OPECUMP'] ?? '0') . ";\n";
    }

    /**
     * @throws Exception
     */
    public function createAbo(
        array $automateE,
        array $automateAA,
        array $automateAB,
        array $automateAE,
        array $automateAF,
        array $automateAL,
        array $lignes,
        int $memoId,
        string $user,
        string $pcvnum
    ): array
    {
        try {
            // Construire le contenu du fichier
            $fileContent = $this->buildAboFileContent(
                $automateE,
                $automateAA,
                $automateAB,
                $automateAE,
                $automateAF,
                $automateAL,
                $lignes,
                $memoId
            );
            $trsid = $this->executeAutomateTask($fileContent, self::AUTOMATE_ENTITY_PIECEVENTE);
            $this->tcpService->sendWaveSoftCommand($trsid);
            
            try {
                if ($this->waitForCompletion($trsid)) {
                    $aboId = $this->connection->executeQuery(
                "SELECT TRSCODEOBJET FROM WSAUTOMATE WHERE TRSID = :trsid", ['trsid' => $trsid])->fetchOne();
                    
                    $this->createWavesoftLog($trsid, $aboId, $fileContent, $user, 'success');
                    $this->deleteWslCodeInWSLOCK($pcvnum);
                    $fileContentET = $this->buildTransformationET($aboId, $pcvnum);
                    $trsidET = $this->executeAutomateTask($fileContentET, self::AUTOMATE_ENTITY_PIECEVENTE);
                    $this->tcpService->sendWaveSoftCommand($trsidET);
                    if ($this->waitForCompletion($trsidET)) {
                        return [
                            'success' => true,
                            'message' => 'Abonnement créé avec succès',
                            'trsid' => $trsid,
                            'aboid' => $aboId
                        ];
                    }
                } else {
                    $result = $this->connection->executeQuery(
                "SELECT TRSERREUR, TRSCODEOBJET FROM WSAUTOMATE WHERE TRSID = :trsid", ['trsid' => $trsid])->fetchOne();
                    
                    $this->createWavesoftLog(
                        $trsid, 
                        $result['TRSCODEOBJET'], 
                        $fileContent, 
                        $user, 
                        'error',
                        $result['TRSERREUR']
                    );
                    return [
                        'success' => false,
                        'message' => 'Le traitement n\'a pas abouti',
                        'trsid' => $trsid
                    ];
                }
            } catch (Exception $e) {
                // Récupérer l'erreur de l'automate si disponible
                try {
                    $result = $this->connection->executeQuery(
                        "SELECT TRSERREUR, TRSCODEOBJET FROM WSAUTOMATE WHERE TRSID = :trsid", 
                        ['trsid' => $trsid]
                    )->fetchAssociative();
                    
                    $errorMsg = $result['TRSERREUR'] ?? $e->getMessage();
                    $aboId = $result['TRSCODEOBJET'] ?? null;
                } catch (Exception $innerEx) {
                    $errorMsg = $e->getMessage();
                    $aboId = null;
                }
                
                // Créer un log d'erreur
                $this->createWavesoftLog(
                    $trsid, 
                    $aboId, 
                    $fileContent, 
                    $user, 
                    'error',
                    $errorMsg
                );
                
                // Générer le message d'erreur formaté pour l'envoi par mail
                $formattedErrorMsg = $this->formatMailtoMessage($trsid);
                return [
                    'success' => false,
                    'message' => $formattedErrorMsg,
                    'trsid' => $trsid,
                    'showMailto' => true
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la création d\'abonnement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Construit le contenu du fichier pour la création d'abonnement
     * 
     * @param array $automateE Données d'entête
     * @param array $automateAA Données client de facturation
     * @param array $automateAB Données d'abonnement
     * @param array $automateAE Données client d'expédition
     * @param array $automateAF Données client final
     * @param array $automateAL Données client de livraison
     * @param array $lignes Liste des lignes d'articles et commentaires
     * @return string Contenu du fichier
     */
    private function buildAboFileContent(
        array $automateE,
        array $automateAA,
        array $automateAB,
        array $automateAE,
        array $automateAF,
        array $automateAL,
        array $lignes,
        int $memoId
    ): string {
        $memoContent = $this->memoRepository->findMemoById($memoId);
        $fileContent = $this->formatAutomateSection($automateE, 'E');
        $fileContent .= $this->formatAutomateSection($automateAF, 'AF');
        $fileContent .= $this->formatAutomateSection($automateAL, 'AL');
        $fileContent .= $this->formatAutomateSection($automateAA, 'AA');
        $fileContent .= $this->formatAutomateSection($automateAB, 'AB');
        $fileContent .= $this->formatAutomateSection($automateAE, 'AE');

        foreach ($lignes as $ligne) {
            if (isset($ligne['TYPE'])) {
                if ($ligne['TYPE'] === 'article') {
                    // Lignes LA (article)
                    if (isset($ligne['automateLA'])) {
                        $fileContent .= $this->formatAutomateSection($ligne['automateLA'], 'LA');
                    }
                    // Lignes LD (détails) si présentes pour cet article
                    if (isset($ligne['automateLD'])) {
                        $fileContent .= $this->formatAutomateSection($ligne['automateLD'], 'LD');
                    }
                } elseif ($ligne['TYPE'] === 'comment') {
                    // Lignes LC (commentaire)
                    if (isset($ligne['automateLC'])) {
                        $fileContent .= $this->formatAutomateSection($ligne['automateLC'], 'LC');
                    }
                }
            }
        }
        $fileContent .= "NO;\"". $memoContent ."\";";
        return $fileContent;
    }
    
    /**
     * Formate une section de l'automate à partir d'un tableau associatif
     * 
     * @param array $section Tableau de données de section
     * @param string $prefix Préfixe de la section (AA, AB, LA, etc.)
     * @return string Section formatée
     */
    private function formatAutomateSection(array $section, string $prefix): string {
        if (isset($section[1]) && $section[1] === $prefix) {
            $values = $section;
        } else {
            $maxElements = match($prefix) {
                'E' => 38,
                'AA' => 23,
                'AB' => 13,
                'AE' => 22,
                'AF' => 23,
                'AL' => 22,
                'LA' => 52,
                'LD' => 9,
                'LC' =>11,
                default => 0
            };

            $values = array_fill(1, $maxElements, '');
            $values[1] = $prefix;

            foreach ($section as $key => $value) {
                if (str_starts_with($key, $prefix)) {
                    $index = (int)substr($key, strlen($prefix));
                    if ($index >= 1 && $index <= $maxElements) {
                        $values[$index] = $value;
                    }
                }
            }
        }

        $formattedValues = array_map(function($value) {
            if (is_numeric($value) && str_contains($value, '.')) {
                if (str_starts_with($value, '.')) {
                    $value = '0' . $value;
                }
                if (floatval($value) == 0) {
                    return '0';
                }
                return str_replace('.', ',', $value);
            }
            return $value;
        }, $values);

        return implode(';', $formattedValues) . ";\n";
    }

    /**
     * Crée un log Wavesoft
     */
    private function createWavesoftLog(
        $trsId, 
        $aboId, 
        string $automateFile, 
        string $user, 
        string $status,
        ?string $messageError = null
    ): void {
        $wavesoftLog = new WavesoftLog();
        $wavesoftLog->setTrsId((int)$trsId);
        
        // Si aboId est null, on utilise 0 comme valeur par défaut
        $wavesoftLog->setAboId($aboId !== null ? (int)$aboId : 0);
        
        $wavesoftLog->setAutomateFile($automateFile);
        $wavesoftLog->setUserName($user);
        $wavesoftLog->setStatus($status);

        if ($messageError !== null) {
            $wavesoftLog->setMessageError($messageError);
        } else {
            // Utiliser une valeur par défaut pour messageError qui ne peut pas être null
            $wavesoftLog->setMessageError('');
        }
        
        $entityManager = $this->wavesoftLogRepository->getEntityManager();
        $entityManager->persist($wavesoftLog);
        $entityManager->flush();
    }

    private function buildTransformationET(string $aboId, string $pcvnum): string
    {
        return "ET;BONCLI->ABOCLI;$pcvnum;$aboId;;";
    }
}
