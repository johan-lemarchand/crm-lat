<?php

namespace App\ODF\Application\Command\CreateManufacturingOrder;

use App\ODF\Application\Command\CheckAffaire\CheckAffaireCommand;
use App\ODF\Application\Command\UpdateMemoAndApi\UpdateMemoAndApiCommand;
use App\ODF\Domain\Repository\MemoRepositoryInterface;
use App\ODF\Domain\Repository\PieceDetailsRepositoryInterface;
use App\ODF\Domain\Service\AutomateServiceInterface;
use App\ODF\Domain\Service\LockServiceInterface;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

readonly class CreateManufacturingOrderHandler
{
    public function __construct(
        private PieceDetailsRepositoryInterface $pieceDetailsRepository,
        private AutomateServiceInterface        $automateService,
        private LoggerInterface                 $logger,
        private MessageBusInterface             $commandBus,
        private MemoRepositoryInterface         $memoRepository,
        private OdfExecutionLogger              $executionLogger,
        private LockServiceInterface            $lockService,
    ) {
    }

    public function __invoke(CreateManufacturingOrderCommand $command): array
    {

        $handlerStartTime = $this->executionLogger->startHandler(
            'CreateManufacturingOrderHandler',
            [
                'pcdid' => $command->getPcdid(),
                'orderNumber' => $command->getOrderNumber(),
                'user' => $command->getUser()
            ]
        );

        try {
            $orderDetails = $this->pieceDetailsRepository->findByPcdid($command->getPcdid());
            $memoId = $orderDetails[0]['MEMOID'] ?? null;

            $this->executionLogger->addStep(
                'Recherche détails commande',
                $orderDetails ? 'success' : 'error',
                $orderDetails ? 'Détails trouvés' : 'Commande non trouvée'
            );

            if (!$orderDetails) {
                $this->executionLogger->finishHandler('CreateManufacturingOrderHandler', $handlerStartTime, [
                    'status' => 'error',
                    'error' => 'Commande non trouvée'
                ]);
                return [
                    'status' => 'error',
                    'messages' => [[
                        'type' => 'error',
                        'message' => 'Commande non trouvée',
                        'status' => 'error'
                    ]]
                ];
            }

            if (empty($command->getActivationResult())) {
                $this->executionLogger->finishHandler('CreateManufacturingOrderHandler', $handlerStartTime, [
                    'status' => 'error',
                    'error' => 'Données d\'activation manquantes'
                ]);
                return [
                    'status' => 'error',
                    'messages' => [[
                        'type' => 'error',
                        'message' => 'Données d\'activation manquantes',
                        'status' => 'error'
                    ]]
                ];
            }
            
            $envelope = $this->commandBus->dispatch(new CheckAffaireCommand($command->getPcdid()));
            $affaireResult = $envelope->last(HandledStamp::class)->getResult();
            $affCode = $affaireResult['affaire']['code'];
            
            $this->executionLogger->addStep(
                'Vérification affaire',
                $affaireResult['status'],
                sprintf('Code affaire: %s', $affCode)
            );
            
            $items = $this->prepareArticlesAndCoupons($orderDetails, $command->getActivationResult());
            $memoText = $this->memoRepository->getMemoText($memoId);

            $this->executionLogger->addStep(
                'Préparation articles',
                'info',
                sprintf('Articles préparés: %d', count($items['articles']))
            );

            $bdfResult = $this->automateService->processFabricationAutomate(
                $items,
                $affCode,
                $command->getOrderNumber(),
                $command->getUser(),
                $memoText
            );

            $this->executionLogger->logApiCall(
                'Automate',
                'processFabricationAutomate',
                [
                    'affCode' => $affCode,
                    'orderNumber' => $command->getOrderNumber(),
                    'itemCount' => count($items['articles'])
                ],
                $bdfResult ? ['success' => true] : null,
                !$bdfResult ? 'Erreur lors de la création du bon de fabrication' : null
            );

            if (!$bdfResult) {
                $this->executionLogger->finishHandler('CreateManufacturingOrderHandler', $handlerStartTime, [
                    'status' => 'error',
                    'error' => 'Erreur lors de la création du bon de fabrication'
                ]);
                return [
                    'status' => 'error',
                    'messages' => [[
                        'type' => 'error',
                        'message' => 'Erreur lors de la création du bon de fabrication',
                        'status' => 'error',
                        'showMailto' => true
                    ]]
                ];
            }

            $this->automateService->deleteWslCodeInWSLOCK($orderDetails[0]['PCDNUM']);
            
            $successMessage = 'Création du bon de fabrication effectuée avec succès';
            $memoMessages = [[
                    'title' => 'Création BDF',
                    'content' => $successMessage,
                    'status' => 'success'
                ]];
                
            $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                $command->getPcdid(),
                $memoId,
                $command->getUser(),
                $memoMessages,
                [
                    'status' => 'success',
                    'orderNumber' => $command->getOrderNumber()
                ]
            ));

            $this->automateService->processCloseODFAutomate($orderDetails[0]['PCDNUM']);
            $coupons= [];
            foreach ($items['articles'] as $article) {
                if (!empty($article['coupons'])) {
                    foreach ($article['coupons'] as $coupon) {
                        $coupons[] = $coupon['OPENUMSERIE'];
                    }
                }
            }
            $this->lockService->unlockSerialNumbers($coupons);
            $this->executionLogger->addStep(
                'Finalisation',
                'success',
                'Bon de fabrication créé et ODF clôturé'
            );

            $this->executionLogger->finishHandler('CreateManufacturingOrderHandler', $handlerStartTime, [
                'status' => 'success',
                'orderNumber' => $command->getOrderNumber()
            ]);

            return [
                'status' => 'success',
                'successfullyClosed' => true,
                'messages' => [
                    [
                        'type' => 'success',
                        'message' => 'Bon de fabrication créé avec succès',
                        'status' => 'success'
                    ],
                    [
                        'type' => 'success',
                        'message' => 'Commande réussie - Traitement terminé',
                        'status' => 'success',
                        'isFinal' => true,
                        'showConfetti' => true
                    ]
                ],
                'data' => [
                    'orderNumber' => $command->getOrderNumber()
                ]
            ];
            
        } catch (\Exception | ExceptionInterface $e) {
            $this->executionLogger->logError(
                'Erreur création BDF',
                'Erreur lors de la création du bon de fabrication',
                $e
            );
            
            // Formater le message d'erreur pour une meilleure lisibilité
            $errorMessage = 'Erreur lors de la création du bon de fabrication';
            
            // Ajouter les détails de l'erreur si disponibles
            if ($e->getMessage()) {
                $errorDetails = $e->getMessage();
                // Nettoyer le message d'erreur pour une meilleure lisibilité
                $errorDetails = str_replace(['SQLSTATE', '[Microsoft]', '[ODBC Driver 18 for SQL Server]', '[SQL Server]'], '', $errorDetails);
                $errorDetails = preg_replace('/\[\d+, \d+\]:/', '', $errorDetails);
                $errorDetails = trim($errorDetails);
                
                $errorMessage .= ":\n" . $errorDetails;
            }
            
            return [
                'status' => 'error',
                'messages' => [[
                    'type' => 'error',
                    'message' => $errorMessage,
                    'status' => 'error',
                    'showMailto' => true
                ]]
            ];
        }
    }
    
    /**
     * Prépare les articles et les coupons pour la création du BDF
     * 
     * @param array $pieceDetails Les détails de la pièce
     * @param array $activationData Les données d'activation
     * @return array Les articles préparés
     */
    private function prepareArticlesAndCoupons(array $pieceDetails, array $activationData): array
    {
        $items = ['articles' => []];
        $articlesById = [];
        
        if (!empty($activationData[0]) && is_string($activationData[0])) {
            $decodedActivations = json_decode($activationData[0], true);
        } else {
            $decodedActivations = $activationData;
        }
        
        $articleIndex = 0;
        
        foreach ($pieceDetails as $piece) {
            if ($piece['PLDTYPE'] === 'L') {
                $piece['PCDNUM'] = $pieceDetails[0]['PCDNUM'];
                $piece['article_index'] = $articleIndex++;
                $articlesById[$piece['PLDSTOTID']] = $piece;
                $articlesById[$piece['PLDSTOTID']]['coupons'] = [];
                $items['articles'][] = &$articlesById[$piece['PLDSTOTID']];
            }
        }
        
        $pcdnum = $pieceDetails[0]['PCDNUM'];
        $usedSerialNumbers = [];
        
        foreach ($pieceDetails as $piece) {
            if ($piece['PLDTYPE'] === 'C' && isset($piece['PLDPEREID'])) {
                $parentId = (int)$piece['PLDPEREID'];
                if (isset($articlesById[$parentId])) {
                    $parentPiece = $articlesById[$parentId];
                    $numCoupons = (int)$piece['PLDQTE'];
                    
                    // Créer autant de coupons que nécessaire
                    for ($i = 0; $i < $numCoupons; $i++) {
                        $coupon = $piece;
                        $searchNumber = "BTR" . $pcdnum;
                        $numeroSerie = $this->pieceDetailsRepository->getNumberSerieCoupon(
                            $piece['ARTID'], 
                            $searchNumber, 
                            $usedSerialNumbers
                        );
                        
                        if ($numeroSerie) {
                            $coupon['OPENUMSERIE'] = $numeroSerie;
                            $usedSerialNumbers[] = $numeroSerie;
                        }
                        
                        $parentSerialNumber = $parentPiece['PLDDIVERS'];
                        
                        $matchingActivation = null;
                        if (isset($decodedActivations[$parentPiece['article_index']])) {
                            $activation = $decodedActivations[$parentPiece['article_index']];
                            if (isset($activation['serialNumber']) && 
                                trim($activation['serialNumber']) === trim($parentSerialNumber)) {
                                $matchingActivation = $activation;
                            }
                        }

                        if ($matchingActivation) {
                            $coupon['serialNumber'] = $matchingActivation['serialNumber'] ?? null;
                            $coupon['partDescription'] = $matchingActivation['partDescription'] ?? null;
                            $coupon['serviceStartDate'] = $matchingActivation['serviceStartDate'] ?? $matchingActivation['activationDate'] ?? null;
                            $coupon['serviceEndDate'] = $matchingActivation['serviceEndDate'] ?? $matchingActivation['expirationDate'] ?? null;
                            $coupon['passcode'] = $matchingActivation['passcode'] ?? null;
                            $isQR = $this->checkPasscode($coupon['passcode'] ?? '');
                            $coupon['qrcode'] = $isQR[1];
                        }
                        
                        $coupon['OPECUMP'] = $this->pieceDetailsRepository->getCouponCump(
                            $piece['ARTID'], 
                            $coupon['OPENUMSERIE'] ?? ''
                        );
                        
                        $articlesById[$parentId]['coupons'][] = $coupon;
                    }
                }
            }
        }
        return $items;
    }
    
    /**
     * Vérifie si un passcode est un QR code
     * 
     * @param string $passcode Le passcode à vérifier
     * @return array [bool, string] Indique si c'est un QR code et le statut (O/N)
     */
    private function checkPasscode(string $passcode): array
    {
        $isQR = [false, "N"];
        if ($passcode === "DeviceNotFound") {
            return $isQR;
        }
        if (str_starts_with($passcode, "l") || str_starts_with($passcode, "[") || str_ends_with($passcode, "=")) {
            $isQR[0] = true;
            $isQR[1] = "O";
        }
        return $isQR;
    }
}
