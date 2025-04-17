<?php

namespace App\ODF\Application\Command\CreateOrder;

use App\ODF\Domain\Service\TrimbleServiceInterface;
use App\ODF\Domain\Service\UniqueIdServiceInterface;
use App\ODF\Domain\Repository\PieceDetailsRepositoryInterface;
use App\ODF\Application\Command\UpdateMemoAndApi\UpdateMemoAndApiCommand;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;

#[AsMessageHandler]
readonly class CreateOrderHandler
{
    public function __construct(
        private TrimbleServiceInterface $trimbleService,
        private UniqueIdServiceInterface $uniqueIdService,
        private PieceDetailsRepositoryInterface $pieceDetailsRepository,
        private LoggerInterface $logger,
        private MessageBusInterface $commandBus,
        private OdfExecutionLogger $executionLogger
    ) {}

    public function __invoke(CreateOrderCommand $command): array
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'CreateOrderHandler',
            ['pcdid' => $command->getPcdid()]
        );

        try {
            $this->executionLogger->addStep(
                'Début création commande',
                'info',
                'Reprise du traitement de la commande'
            );

            $orderDetails = $this->pieceDetailsRepository->findByPcdid($command->getPcdid());
            if (empty($orderDetails)) {
                throw new \Exception('Aucun détail trouvé pour cette pièce');
            }
            
            $memoId = $orderDetails[0]['MEMOID'] ?? throw new \Exception('MemoId non trouvé');

            $this->executionLogger->addStep(
                'Création commande Trimble',
                'info',
                'Envoi de la commande vers Trimble'
            );

            $orderResult = $this->trimbleService->createOrder($orderDetails);
            $this->executionLogger->logApiCall(
                'Trimble',
                'createOrder',
                ['orderDetails' => count($orderDetails) . ' lignes'],
                $orderResult,
                empty($orderResult['uniqueId']) ? 
                    ($orderResult['fault']['description'] ?? ($orderResult['errorMessage'] ?? "La création de la commande a échoué")) : 
                    null
            );

            if (empty($orderResult['uniqueId'])) {
                $errorMessage = $orderResult['fault']['description'] ?? ($orderResult['errorMessage'] ?? "La création de la commande a échoué");

                $this->executionLogger->addStep(
                    'Échec création commande',
                    'error',
                    $errorMessage
                );
                
                $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                    $command->getPcdid(),
                    $memoId,
                    $command->getUser(),
                    [[
                        'title' => 'Erreur commande',
                        'content' => $errorMessage,
                        'status' => 'error'
                    ]],
                    [
                        'status' => 'error',
                        'message' => $errorMessage,
                        'apiDetails' => $orderResult
                    ]
                ));

                $this->executionLogger->finishHandler('CreateOrderHandler', $handlerStartTime, [
                    'status' => 'error',
                    'error' => $errorMessage
                ]);
                
                return [
                    'status' => 'error',
                    'messages' => [[
                        'type' => 'error',
                        'message' => $errorMessage,
                        'status' => 'error',
                        'isCreationError' => true,
                        'apiDetails' => $orderResult
                    ]]
                ];
            }

            $uniqueId = $orderResult['uniqueId'];
            
            // Mise à jour de l'ID unique
            $this->uniqueIdService->updateUniqueId($command->getPcdid(), $uniqueId);

            $successMessage = 'Commande créée avec succès';
            $this->executionLogger->addStep(
                'Commande créée',
                'success',
                $successMessage . " - ID: " . $uniqueId
            );

            $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                $command->getPcdid(),
                $memoId,
                $command->getUser(),
                [[
                    'title' => 'Création commande',
                    'content' => $successMessage . " - ID: " . $uniqueId,
                    'status' => 'success'
                ]],
                [
                    'status' => 'success',
                    'uniqueId' => $uniqueId,
                    'message' => $successMessage
                ]
            ));

            $this->executionLogger->finishHandler('CreateOrderHandler', $handlerStartTime, [
                'status' => 'success',
                'uniqueId' => $uniqueId
            ]);

            return [
                'status' => 'success',
                'messages' => [[
                    'type' => 'success',
                    'message' => $successMessage . " - ID: " . $uniqueId,
                    'status' => 'success'
                ]],
                'uniqueId' => $uniqueId
            ];

        } catch (\Exception | ExceptionInterface $e) {
            $errorMessage = $e->getMessage();
            $this->executionLogger->logError(
                'Erreur création commande',
                'Erreur lors de la création de la commande',
                $command->getUser(),
                $e
            );
            
            try {
                $orderDetails = $this->pieceDetailsRepository->findByPcdid($command->getPcdid());
                if (!empty($orderDetails) && isset($orderDetails[0]['MEMOID'])) {
                    $memoId = $orderDetails[0]['MEMOID'];
                    
                    $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                        $command->getPcdid(),
                        $memoId,
                        $command->getUser(),
                        [[
                            'title' => 'Erreur commande',
                            'content' => $errorMessage,
                            'status' => 'error'
                        ]],
                        [
                            'status' => 'error',
                            'message' => $errorMessage
                        ]
                    ));
                }
            } catch (\Exception | ExceptionInterface $memoEx) {
                $this->logger->error('Erreur lors de la mise à jour du mémo', [
                    'error' => $memoEx->getMessage()
                ]);
                $this->executionLogger->logError(
                    'Erreur mise à jour mémo',
                    'Erreur lors de la mise à jour du mémo',
                    $command->getUser(),
                    $memoEx
                );
            }
            
            // Marquer la fin du handler avec un statut d'erreur
            $this->executionLogger->finishHandler('CreateOrderHandler', $handlerStartTime, [
                'status' => 'error',
                'message' => $errorMessage
            ], 2); // 2 = Statut d'erreur

            return [
                'status' => 'error',
                'messages' => [[
                    'type' => 'error',
                    'message' => $errorMessage,
                    'status' => 'error',
                    'isCreationError' => true
                ]]
            ];
        }
    }
}
