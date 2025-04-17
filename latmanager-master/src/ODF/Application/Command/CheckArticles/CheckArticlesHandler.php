<?php

namespace App\ODF\Application\Command\CheckArticles;

use App\ODF\Domain\Repository\PieceDetailsRepositoryInterface;
use App\ODF\Domain\Service\ArticleValidationService;
use App\ODF\Domain\Service\TrimbleServiceInterface;
use App\ODF\Infrastructure\Service\Timer;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CheckArticlesHandler
{
    public function __construct(
        private ArticleValidationService $articleValidationService,
        private TrimbleServiceInterface $trimbleService,
        private Timer $timer,
        private PieceDetailsRepositoryInterface $pieceDetailRepository,
        private OdfExecutionLogger $executionLogger
    ) {}

    public function __invoke(CheckArticlesCommand $command): array
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'CheckArticlesHandler',
            ['pieceDetails' => count($command->getPieceDetails())]
        );

        $this->timer->logStep(
            "Début de la vérification des articles",
            'info',
            'pending',
            "Démarrage de la vérification des détails des articles"
        );

        $pieceDetails = $command->getPieceDetails();
        $detailedCheck = [];
        $hasError = false;
        $errorDetails = [];
        
        $articleData = $this->preprocessArticleData($pieceDetails);

        if (isset($articleData['hasError'])) {
            $hasError = true;
            $errorDetails = array_merge($errorDetails, $articleData['errorDetails']);
            $this->executionLogger->addStep(
                'Prétraitement des articles',
                'error',
                json_encode($errorDetails)
            );
        } else {
            $this->executionLogger->addStep(
                'Prétraitement des articles',
                'success',
                sprintf('Total articles: %d, Quantité totale: %d', 
                    count($articleData['demandedQuantities']), 
                    $articleData['totalQuantity']
                )
            );
        }

        // Vérification de la quantité totale
        if ($articleData['totalQuantity'] > 20) {
            $hasError = true;
            $errorDetails[] = [
                'type' => 'quantite_globale',
                'message' => sprintf(
                    "La quantité totale (%d) dépasse la limite de 20 pour cette commande",
                    $articleData['totalQuantity']
                ),
                'details' => [
                    'quantite_demandee' => $articleData['totalQuantity'],
                    'limite' => 20
                ]
            ];
            $this->executionLogger->addStep(
                'Vérification quantité totale',
                'error',
                sprintf('Quantité %d > limite 20', $articleData['totalQuantity'])
            );
        }

        $stockStatus = $this->pieceDetailRepository->getQuantityCheck($pieceDetails[0]['PCDID']);
        $stockByArticle = array_column($stockStatus, 'ARDSTOCKREEL', 'ARTCODE');
        foreach ($articleData['demandedQuantities'] as $articleCode => $quantity) {
            if (isset($stockByArticle[$articleCode]) && $stockByArticle[$articleCode] < $quantity) {
               $hasError = true;
                $errorDetails[] = [
                    'type' => 'error',
                    'message' => sprintf(
                        "Stock insuffisant pour l'article %s (Stock: %d, Demandé: %d)",
                        $articleCode,
                        $stockByArticle[$articleCode],
                        $quantity
                    )
                ];
            }
        }

        // Vérification des numéros de série si nécessaire
        if (!empty($articleData['serialsToCheck'])) {
            $serialResponses = $this->checkSerialNumbers($articleData['serialsToCheck']);
            foreach ($serialResponses as $lineNumber => $response) {
                $this->executionLogger->logApiCall(
                    'Trimble',
                    'checkSerialNumber',
                    ['serialNumber' => $articleData['serialsToCheck'][$lineNumber]['serialNumber']],
                    $response,
                    $response['status'] === 'error' ? $response['message'] ?? null : null
                );
            }
        }

        // Traitement des lignes articles
        foreach ($pieceDetails as $row) {
            if ($row['PLDTYPE'] === 'L') {
                $detailedCheck[] = $this->processArticleLine(
                    $row,
                    $articleData['eligibleArticles'][$row['PLDNUMLIGNE']] ?? false,
                    $serialResponses[$row['PLDNUMLIGNE']] ?? null,
                    $hasError,
                    $errorDetails
                );
            }
        }

        // On trie le tableau par numéro de ligne
        usort($detailedCheck, function($a, $b) {
            return $a['ligne'] <=> $b['ligne'];
        });

        $this->executionLogger->finishHandler('CheckArticlesHandler', $handlerStartTime, [
            'status' => $hasError ? 'error' : 'success',
            'errorCount' => count($errorDetails),
            'checkedArticles' => count($detailedCheck)
        ]);

        return [
            'status' => $hasError ? 'error' : 'success',
            'messages' => $errorDetails,
            'details' => $detailedCheck
        ];
    }

    private function preprocessArticleData(array $pieceDetails): array
    {
        $data = [
            'demandedQuantities' => [],
            'eligibleArticles' => [],
            'serialsToCheck' => [],
            'totalQuantity' => 0,
            'stockCheck' => []
        ];

        foreach ($pieceDetails as $row) {
            if ($row['PLDTYPE'] === 'C') {
                $articleCode = $this->articleValidationService->getArticleForCoupon($row['ARTCODE']);
                if ($articleCode !== null && $this->articleValidationService->isArticleAuthorized($articleCode)) {
                    $data['demandedQuantities'][$row['ARTCODE']] = 
                        ($data['demandedQuantities'][$row['ARTCODE']] ?? 0) + $row['PLDQTE'];
                    $data['totalQuantity'] += $row['PLDQTE'];
                }

                $data['stockCheck'][$row['ARTCODE']] = [
                    'stock' => $row['ARDSTOCKREEL'] ?? 0,
                    'demande' => $row['PLDQTE']
                ];
            } elseif ($row['PLDTYPE'] === 'L') {
                if ($row['PLDQTE'] <= 0) {
                    $data['hasError'] = true;
                    $data['errorDetails'][] = [
                        'type' => 'quantite_invalide',
                        'message' => sprintf(
                            "La quantité doit être supérieure à 0 pour l'article %s (ligne %s)",
                            $row['ARTCODE'],
                            $row['PLDNUMLIGNE']
                        ),
                        'details' => [
                            'quantite' => $row['PLDQTE'],
                            'article' => $row['ARTCODE'],
                            'ligne' => $row['PLDNUMLIGNE']
                        ]
                    ];
                }
                $data['eligibleArticles'][$row['PLDNUMLIGNE']] = 
                    $this->articleValidationService->isArticleAuthorized($row['ARTCODE']);
                if (!empty($row['PLDDIVERS'])) {
                    $data['serialsToCheck'][$row['PLDNUMLIGNE']] = [
                        'serialNumber' => $row['PLDDIVERS'],
                        'manufacturerModel' => $row['MFGMODELE']
                    ];
                }
            }
        }

        foreach ($data['stockCheck'] as $artcode => $stockInfo) {
            if ($stockInfo['stock'] < $stockInfo['demande']) {
                $data['hasError'] = true;
                $data['errorDetails'][] = [
                    'type' => 'error',
                    'message' => sprintf(
                        "Stock insuffisant pour le coupon %s (Stock: %d, Demandé: %d)",
                        $artcode,
                        $stockInfo['stock'],
                        $stockInfo['demande']
                    )
                ];
            }
        }

        return $data;
    }

    private function checkSerialNumbers(array $serialsToCheck): array
    {
        $this->executionLogger->addStep(
            'Vérification numéros de série',
            'info',
            sprintf('Vérification de %d numéros de série', count($serialsToCheck))
        );

        $responses = [];
        try {
            foreach ($serialsToCheck as $lineNumber => $serialData) {
                if (!empty($serialData['manufacturerModel'])) {
                    $responses[$lineNumber] = [
                        'status' => 'success',
                        'orderLine' => [
                            'serialNumber' => $serialData['serialNumber'],
                            'manufacturerModel' => $serialData['manufacturerModel']
                        ]
                    ];
                } else {
                    $trimbleResponse = $this->trimbleService->checkSerialNumber($serialData['serialNumber']);
                    $this->executionLogger->logApiCall(
                        'Trimble',
                        'checkSerialNumber',
                        ['serialNumber' => $serialData['serialNumber']],
                        $trimbleResponse,
                        isset($trimbleResponse['status']) && $trimbleResponse['status'] === 'error' ? 
                            $trimbleResponse['message'] ?? null : null
                    );
                    
                    if ($trimbleResponse['status'] === 'success' &&
                        isset($trimbleResponse['orderLine']['orderLine'][0])) {
                        $orderLine = $trimbleResponse['orderLine']['orderLine'][0];
                        $this->pieceDetailRepository->saveSerialNumberAndModel(
                            $serialData['serialNumber'],
                            $orderLine['manufacturerModel']
                        );
                        $responses[$lineNumber] = [
                            'status' => 'success',
                            'orderLine' => [
                                'serialNumber' => $serialData['serialNumber'],
                                'manufacturerModel' => $orderLine['manufacturerModel']
                            ]
                        ];
                    } else {
                        $responses[$lineNumber] = $trimbleResponse;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->executionLogger->logError(
                'Erreur vérification numéros de série',
                'Erreur lors de la vérification des numéros de série',
                $e
            );
            foreach ($serialsToCheck as $lineNumber => $serialData) {
                $responses[$lineNumber] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return $responses;
    }

    public function processArticleLine(array $row, bool $isEligible, ?array $serialResponse, bool &$hasError, array &$errorDetails): array
    {
        try {
            if (!$isEligible) {
                $hasError = true;
                $errorDetails[] = [
                    'type' => 'error',
                    'message' => sprintf(
                        "Article %s non éligible (ligne %s)",
                        $row['ARTCODE'],
                        $row['PLDNUMLIGNE']
                    )
                ];
            }

            $serieStatus = $this->getSerieStatus($row, $serialResponse, $hasError, $errorDetails);

            if (!empty($row['PLDDIVERS'])) {
                $response = $this->trimbleService->getActivationBySerial($row['PLDDIVERS']);
            } else {
                return [
                'status' => 'error',
                'message' => 'Ne peut pas être vide',
                'message_api' => null
            ];
            }

            // Traitement de la réponse des activations
            if ($response['status'] === 'error' ||
                (isset($response['statusCode']) && $response['statusCode'] !== '200') ||
                (isset($response['status']) && $response['status'] === 'Error'))
            {
                $dateEndSubs = 'Pas d\'abonnement en cours';
                $partDescription = '';
                $estimatedStartDate = date('d/m/Y');
            } else {
                [$dateEndSubs, $partDescription, $estimatedStartDate] = $this->processActivationResponse($response);
            }

            // Récupération du modèle
            $manufacturerModel = null;
            if ($serialResponse && $serialResponse['status'] === 'success') {
                $manufacturerModel = $serialResponse['orderLine']['manufacturerModel'] ?? null;
            }

            $lastEnterStock = $this->pieceDetailRepository->CheckLastEnterStockDate($row['PLDDIVERS'], $row['ARTID']);
            $isLastEnterStockDateValid = isset($lastEnterStock['OPEDATE']) && $this->isLastEnterStockDateValid($lastEnterStock['OPEDATE']);
            // Construction de la réponse
            return [
                'ligne' => $row['PLDNUMLIGNE'],
                'quantite' => $row['PLDQTE'],
                'article' => [
                    'code' => $row['ARTCODE'],
                    'designation' => $row['ARTDESIGNATION'],
                    'coupon' => $this->articleValidationService->getCouponForArticle($row['ARTCODE']),
                    'eligible' => true
                ],
                'serie' => [
                    'numero' => $row['PLDDIVERS'],
                    'status' => $serieStatus['status'],
                    'message' => $serieStatus['message'],
                    'message_api' => $serieStatus['message_api'],
                    'manufacturerModel' => $manufacturerModel
                ],
                'date_end_subs' => $dateEndSubs,
                'partDescription' => $partDescription,
                'estimate_start_date' => $estimatedStartDate,
                'lastEnterStockDate' => $lastEnterStock['OPEDATE'] ?? null,
                'refPiece' => $lastEnterStock['OPEREFPIECE'] ?? null,
                'isLastEnterStockDateValid' => $isLastEnterStockDateValid
            ];
        } catch (\Exception $e) {
            $hasError = true;
            $errorDetails[] = [
                'type' => 'error',
                'message' => $e->getMessage(),
                'title' => 'Contrôle du numéro de série'
            ];

            return [
                'ligne' => $row['PLDNUMLIGNE'],
                'quantite' => $row['PLDQTE'],
                'article' => [
                    'code' => $row['ARTCODE'],
                    'designation' => $row['ARTDESIGNATION'],
                    'coupon' => $this->articleValidationService->getCouponForArticle($row['ARTCODE']),
                    'eligible' => true
                ],
                'serie' => [
                    'numero' => $row['PLDDIVERS'],
                    'status' => 'error',
                    'message' => 'Erreur lors de la vérification',
                    'message_api' => $e->getMessage(),
                    'manufacturerModel' => null
                ],
                'date_end_subs' => 'Pas d\'abonnement en cours',
                'partDescription' => '',
                'estimate_start_date' => date('d/m/Y')
            ];
        }
    }

    private function getSerieStatus(array $row, ?array $serialResponse, bool &$hasError, array &$errorDetails): array
    {
        if (empty($row['PLDDIVERS'])) {
            $hasError = true;
            $errorDetails[] = [
                'type' => 'error',
                'message' => sprintf("Numéro de série manquant ligne %s", $row['PLDNUMLIGNE']),
                'title' => 'Contrôle du numéro de série'
            ];
            return [
                'status' => 'error',
                'message' => 'Ne peut pas être vide',
                'message_api' => null
            ];
        }

        if ($serialResponse && $serialResponse['status'] === 'success') {
            return [
                'status' => 'success',
                'message' => 'Numéro de série valide',
                'message_api' => null
            ];
        }

        $hasError = true;
        $errorMessage = $this->formatErrorMessage($serialResponse);
        $errorDetails[] = [
            'type' => 'error',
            'message' => sprintf("Numéro de série invalide '%s' ligne %s : Erreur API Trimble", 
                $row['PLDDIVERS'], 
                $row['PLDNUMLIGNE']
            ),
            'title' => 'Contrôle du numéro de série'
        ];

        return [
            'status' => 'error',
            'message' => 'Invalide chez Trimble',
            'message_api' => $errorMessage
        ];
    }

    private function processActivationResponse(?array $response): array
    {
        if ($response === null || 
            $response['status'] === 'error' || 
            (isset($response['statusCode']) && $response['statusCode'] !== '200') ||
            (isset($response['status']) && $response['status'] === 'Error'))
        {
            $this->timer->logStep(
                "Pas d'abonnement",
                'info',
                'info',
                "Aucun abonnement en cours trouvé"
            );
            return [
                'Pas d\'abonnement en cours',
                '',
                date('d-m-Y')
            ];
        }

        $latestActivation = null;
        $latestDate = null;
        
        foreach ($response['activations'] as $activation) {
            $currentDate = strtotime($activation['serviceEndDate']);
            if ($latestDate === null || $currentDate > $latestDate) {
                $latestDate = $currentDate;
                $latestActivation = $activation;
            }
        }
        
        if ($latestActivation) {
            return [
                date('d/m/Y', strtotime($latestActivation['serviceEndDate'])),
                $latestActivation['partDescription'],
                date('d/m/Y', strtotime($latestActivation['serviceEndDate']))
            ];
        }

        return [
            'Pas d\'abonnement en cours',
            '',
            date('d/m/Y')
        ];
    }

    private function formatErrorMessage($response): string
    {
        if (!isset($response['message'])) {
            return "No records found for Requested parameters";
        }

        if (str_contains($response['message'], 'Erreur API Trimble')) {
            $jsonStr = substr($response['message'], strpos($response['message'], '{'));
            $jsonError = json_decode($jsonStr, true);
            if ($jsonError && isset($jsonError['errorMessage'][0]['error'])) {
                $messages = array_filter(explode(';', $jsonError['errorMessage'][0]['error']));
                return reset($messages);
            }
        }

        $messages = array_filter(explode(';', $response['message']));
        return reset($messages);
    }

    /**
     * Vérifie si la date d'entrée en stock est valide (plus ancienne que 10 mois)
     */
    private function isLastEnterStockDateValid(?string $dateString): bool
    {
        // Si la date est vide ou null, on considère qu'elle n'est pas valide
        if (empty($dateString)) {
            return false;
        }

        try {
            // Convertir la chaîne de date en objet DateTime
            $lastEnterDate = new \DateTime($dateString);
            
            // Date limite (aujourd'hui moins 10 mois)
            $limitDate = (new \DateTime())->modify('-10 months');
            
            // La date est considérée comme valide (à mettre en rouge) si elle est AVANT la date limite (plus ancienne que 10 mois)
            return $lastEnterDate > $limitDate;
        } catch (\Exception $e) {
            // En cas d'erreur de format de date, considérer comme non valide
            return false;
        }
    }
} 