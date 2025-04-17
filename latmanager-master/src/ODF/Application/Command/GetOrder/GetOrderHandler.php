<?php

namespace App\ODF\Application\Command\GetOrder;

use App\ODF\Domain\Repository\OrderRepositoryInterface;
use App\ODF\Domain\Repository\PieceDetailsRepositoryInterface;
use App\ODF\Domain\Service\TrimbleServiceInterface;
use App\Shared\Service\Timer;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetOrderHandler
{
    private const MAX_RETRIES = 80;

    public function __construct(
        private TrimbleServiceInterface $trimbleService,
        private OrderRepositoryInterface $orderRepository,
        private PieceDetailsRepositoryInterface $pieceDetailsRepository,
        private OdfExecutionLogger $executionLogger
    ) {}

    public function __invoke(GetOrderCommand $command): array
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'GetOrderHandler',
            ['pcdid' => $command->getPcdid()]
        );

        try {
            $orderDetails = $this->pieceDetailsRepository->findByPcdid($command->getPcdid());
            $uniqueIdResult = $this->pieceDetailsRepository->findUniqueIdByPcdid($command->getPcdid());
            $uniqueId = $uniqueIdResult['UNIQUEID'];
            
            $this->executionLogger->addStep(
                'Récupération des détails',
                'info',
                sprintf('Début récupération commande %s', $uniqueId)
            );

            $countFile = sys_get_temp_dir() . "/getorder_count_{$uniqueId}.txt";
            
            if (file_exists($countFile)) {
                $currentTry = (int)file_get_contents($countFile);
                $currentTry++;
            } else {
                $currentTry = 1;
            }
            // Sauvegarder le compteur pour la prochaine requête
            file_put_contents($countFile, $currentTry);

            // Appel à l'API Trimble
            $result = $this->trimbleService->getOrderByUniqueId($uniqueId);
            $this->executionLogger->logApiCall(
                'Trimble',
                'getOrderByUniqueId',
                ['uniqueId' => $uniqueId],
                $result,
                !$this->isValidOrder($result) ? 'Commande non valide ou non trouvée' : null
            );

            if ($this->isValidOrder($result)) {
                // Nettoyer le fichier de compteur
                @unlink($countFile);
                
                $orderNumber = $result['orderHdr']['orderNumber'];
                $this->orderRepository->updateOrderNumber($command->getPcdid(), $orderNumber);
                
                // Formater les items pour le frontend
                $formattedItems = $this->formatOrderItems($orderDetails, $result);
                
                $successMessage = "Commande Trimble N°: $orderNumber récupérée avec succès après $currentTry tentatives.";
                $this->executionLogger->addStep(
                    'Récupération réussie',
                    'success',
                    $successMessage
                );

                $this->executionLogger->finishHandler('GetOrderHandler', $handlerStartTime, [
                    'status' => 'success',
                    'orderNumber' => $orderNumber,
                    'attempts' => $currentTry
                ]);

                return [
                    'status' => 'success',
                    'message' => $successMessage,
                    'data' => [
                        'orderNumber' => $orderNumber,
                        'eventDataGetOrder' => [
                            'orderHdr' => [
                                'items' => $formattedItems
                            ]
                        ]
                    ]
                ];
            }
            
            if ($currentTry >= self::MAX_RETRIES) {
                // Nettoyer le fichier de compteur
                @unlink($countFile);
                
                $errorMessage = "Impossible de récupérer la commande après " . self::MAX_RETRIES . " tentatives";
                $this->executionLogger->addStep(
                    'Limite de tentatives atteinte',
                    'error',
                    $errorMessage
                );

                $this->executionLogger->finishHandler('GetOrderHandler', $handlerStartTime, [
                    'status' => 'error',
                    'attempts' => $currentTry,
                    'maxRetries' => self::MAX_RETRIES
                ]);
                
                return [
                    'status' => 'error',
                    'message' => $errorMessage
                ];
            }

            // Sinon, on continue avec les tentatives
            $infoMessage = "Tentative {$currentTry}/" . self::MAX_RETRIES . " - En attente de la récupération de la commande chez Trimble";
            $this->executionLogger->addStep(
                'Tentative en cours',
                'info',
                $infoMessage
            );

            return [
                'status' => 'pending',
                'type' => 'retry',
                'retry' => true,
                'retryCount' => $currentTry,
                'maxRetries' => self::MAX_RETRIES,
                'message' => $infoMessage
            ];

        } catch (\Exception $e) {
            $this->executionLogger->logError(
                'Erreur récupération commande',
                'Erreur lors de la récupération de la commande',
                $e
            );
            
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function isValidOrder(array $result): bool
    {
        return $result &&
            isset($result['status']) &&
            $result['status'] === 'Success' &&
            isset($result['orderHdr']['orderNumber']) &&
            $result['orderHdr']['orderNumber'] !== 'NA';
    }

    private function formatOrderItems(array $orderDetails, array $result): array
    {
        $groupedItems = [];
        
        // Créer un tableau associatif pour faciliter la recherche des coupons
        $couponsMap = [];
        $articlesMap = [];
        $trimbleToLocalArticleMap = [];
        
        // Première passe : indexer les articles et les coupons par PLDDIVERS (numéro de série)
        foreach ($orderDetails as $detail) {
            $serialNumber = $detail['PLDDIVERS'] ?? '';
            $articleCode = $detail['ARTCODE'] ?? '';
            $proCode = $detail['PROCODE'] ?? '';
            $pldType = $detail['PLDTYPE'] ?? '';

            // Créer une correspondance entre PROCODE (Trimble) et ARTCODE (local)
            if (!empty($proCode)) {
                $trimbleToLocalArticleMap[$proCode] = $articleCode;
            }
            
            if ($pldType === 'L') {
                // C'est un article principal
                if (!isset($articlesMap[$serialNumber])) {
                    $articlesMap[$serialNumber] = [];
                }
                $articlesMap[$serialNumber][] = $detail;
            } else if ($pldType === 'C') {
                // C'est un coupon
                if (!isset($couponsMap[$serialNumber])) {
                    $couponsMap[$serialNumber] = [];
                }
                $couponsMap[$serialNumber][] = $detail;
            }
        }
        
        if (isset($result['orderLine']) && is_array($result['orderLine'])) {
            // Deuxième passe : regrouper par numéro de série et référence article
            foreach ($result['orderLine'] as $line) {
                $serialNumber = $line['serialNumber'] ?? '';
                $partNumber = $line['partNumber'] ?? '';
                $key = $serialNumber . '_' . $partNumber;
                
                // Trouver l'ARTCODE correspondant au PROCODE de Trimble
                $localArticleCode = $trimbleToLocalArticleMap[$partNumber] ?? $partNumber;
                
                // Chercher le coupon correspondant
                $coupon = null;
                if (isset($couponsMap[$serialNumber])) {
                    foreach ($couponsMap[$serialNumber] as $couponDetail) {
                        // Vérifier si le coupon correspond à l'article
                        $parentCode = $couponDetail['PROCODE_PARENT'] ?? '';
                        if ($parentCode === $partNumber || str_starts_with($couponDetail['ARTCODE'], $localArticleCode)) {
                            $coupon = $couponDetail['ARTCODE'];
                            break;
                        }
                    }
                }
                
                if (!isset($groupedItems[$key])) {
                    // Trouver le numéro de ligne dans les détails de la commande
                    $ligne = '';
                    $designation = '';
                    if (isset($articlesMap[$serialNumber])) {
                        foreach ($articlesMap[$serialNumber] as $articleDetail) {
                            if ($articleDetail['PROCODE'] === $partNumber || $articleDetail['ARTCODE'] === $localArticleCode) {
                                $ligne = $articleDetail['PLDNUMLIGNE'];
                                $designation = $articleDetail['ARTDESIGNATION'];
                                break;
                            }
                        }
                    }
                    
                    $groupedItems[$key] = [
                        'ligne' => $ligne ?: ($line['lineNumber'] ?? ''),
                        'article' => $localArticleCode,
                        'coupon' => $coupon,
                        'designation' => $designation,
                        'quantite' => (int)($line['quantity'] ?? 0),
                        'n_serie' => $serialNumber,
                        'modele' => $line['manufacturerModel'] ?? '',
                        'date_fin' => $line['serviceEndDate'] ?? ''
                    ];
                } else {
                    // Additionner les quantités
                    $groupedItems[$key]['quantite'] += (int)($line['quantity'] ?? 0);

                    // Prendre la date de fin la plus éloignée
                    if ($line['serviceEndDate'] &&
                        (!$groupedItems[$key]['date_fin'] ||
                         $line['serviceEndDate'] > $groupedItems[$key]['date_fin'])) {
                        $groupedItems[$key]['date_fin'] = $line['serviceEndDate'];
                    }
                }
            }
        }
        
        // Convertir le tableau associatif en tableau indexé
        $result = array_values($groupedItems);
        
        // Trier le tableau par numéro de ligne
        usort($result, function($a, $b) {
            $ligneA = (int)$a['ligne'];
            $ligneB = (int)$b['ligne'];
            return $ligneA <=> $ligneB;
        });
        
        return $result;
    }
} 