<?php

namespace App\ODF\Application\Command\ProcessPasscodes;

use App\ODF\Application\Command\UpdateMemoAndApi\UpdateMemoAndApiCommand;
use App\ODF\Domain\Repository\PasscodeRepositoryInterface;
use App\ODF\Domain\Repository\PieceDetailsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;

#[AsMessageHandler]
readonly class ProcessPasscodesHandler
{
    public function __construct(
        private PasscodeRepositoryInterface $passcodeRepository,
        private PieceDetailsRepositoryInterface $pieceDetailsRepository,
        private LoggerInterface $logger,
        private MessageBusInterface $commandBus,
        private OdfExecutionLogger $executionLogger
    ) {}

    public function __invoke(ProcessPasscodesCommand $command): array
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'ProcessPasscodesHandler',
            [
                'pcdid' => $command->getPcdid(),
                'orderNumber' => $command->getOrderNumber(),
                'pcdnum' => $command->getPcdnum()
            ]
        );

        $this->logger->info('Récupération des informations d\'activation pour la commande', [
            'pcdid' => $command->getPcdid(),
            'user' => $command->getUser(),
            'orderNumber' => $command->getOrderNumber(),
            'pcdnum' => $command->getPcdnum()
        ]);

        try {
            // Récupérer les détails de la pièce pour obtenir le memoId
            $pieceDetails = $this->pieceDetailsRepository->findByPcdid($command->getPcdid());
            if (empty($pieceDetails)) {
                throw new \Exception('Aucun détail trouvé pour cette pièce');
            }
            
            $memoId = $pieceDetails[0]['MEMOID'] ?? throw new \Exception('MemoId non trouvé');

            // Récupérer les informations d'activation depuis le repository
            $activationInfo = $this->passcodeRepository->getPasscodes($command->getOrderNumber());
            $this->executionLogger->addStep(
                'Récupération passcodes',
                empty($activationInfo) ? 'info' : 'success',
                empty($activationInfo) ? 'Aucun passcode disponible' : sprintf('%d passcodes trouvés', count($activationInfo))
            );

            // Si aucune information d'activation n'est disponible, simuler un retry
            if (empty($activationInfo)) {
                // Vérifier si c'est un retry ou la première tentative
                $retryCount = $_GET['retryCount'] ?? 0;
                $maxRetries = 20;
                
                if ($retryCount < $maxRetries) {
                    $infoMessage = 'En attente des informations d\'activation...';
                    
                    $this->executionLogger->addStep(
                        'Tentative de récupération',
                        'info',
                        sprintf('Tentative %d/%d', $retryCount, $maxRetries)
                    );
                    
                    // Mettre à jour le mémo avec l'information de tentative
                    $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                        $command->getPcdid(),
                        $memoId,
                        $command->getUser(),
                        [[
                            'title' => 'Récupération passcodes',
                            'content' => $infoMessage . " (Tentative $retryCount/$maxRetries)",
                            'status' => 'pending'
                        ]],
                        [
                            'status' => 'pending',
                            'message' => $infoMessage,
                            'retryCount' => $retryCount,
                            'maxRetries' => $maxRetries
                        ]
                    ));
                    
                    return [
                        'status' => 'pending',
                        'type' => 'retry',
                        'retry' => true,
                        'retryCount' => $retryCount + 1,
                        'maxRetries' => $maxRetries,
                        'message' => $infoMessage,
                        'messages' => [
                            [
                                'type' => 'retry',
                                'message' => $infoMessage,
                                'status' => 'pending'
                            ]
                        ]
                    ];
                }
                
                // Si on a dépassé le nombre de tentatives, retourner une erreur
                $errorMessage = 'Impossible de récupérer les informations d\'activation après plusieurs tentatives';
                
                $this->executionLogger->addStep(
                    'Limite de tentatives atteinte',
                    'error',
                    sprintf('Échec après %d tentatives', $maxRetries)
                );
                
                // Mettre à jour le mémo avec l'erreur
                $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                    $command->getPcdid(),
                    $memoId,
                    $command->getUser(),
                    [[
                        'title' => 'Erreur passcodes',
                        'content' => $errorMessage,
                        'status' => 'error'
                    ]],
                    [
                        'status' => 'error',
                        'message' => $errorMessage
                    ]
                ));

                $this->executionLogger->finishHandler('ProcessPasscodesHandler', $handlerStartTime, [
                    'status' => 'error',
                    'error' => $errorMessage,
                    'attempts' => $maxRetries
                ]);
                
                return [
                    'status' => 'error',
                    'messages' => [
                        [
                            'type' => 'error',
                            'message' => $errorMessage,
                            'status' => 'error'
                        ]
                    ]
                ];
            }
            
            // Ajouter les codes articles pour chaque numéro de série
            foreach ($activationInfo as &$activation) {
                if (!empty($activation['serialNumber'])) {
                    $activation['artcode'] = $this->getArtcodeForSerial($activation['serialNumber'], $command->getPcdnum());
                }
            }
            
            // Succès - Mettre à jour le mémo avec les informations d'activation
            $successMessage = 'Informations d\'activation récupérées avec succès';
            
            $this->executionLogger->addStep(
                'Association articles-passcodes',
                'success',
                sprintf('%d activations traitées', count($activationInfo))
            );
            
            $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                $command->getPcdid(),
                $memoId,
                $command->getUser(),
                [[
                    'title' => 'Passcodes récupérés',
                    'content' => $successMessage . " - " . count($activationInfo) . " activation(s)",
                    'status' => 'success'
                ]],
                [
                    'status' => 'success',
                    'message' => $successMessage,
                    'activationCount' => count($activationInfo)
                ]
            ));

            $this->executionLogger->finishHandler('ProcessPasscodesHandler', $handlerStartTime, [
                'status' => 'success',
                'activationCount' => count($activationInfo)
            ]);
            
            // Retourner les informations d'activation
            return [
                'status' => 'success',
                'messages' => [
                    [
                        'type' => 'success',
                        'message' => $successMessage,
                        'status' => 'success'
                    ]
                ],
                'data' => [
                    'orderNumber' => $command->getOrderNumber(),
                    'activationDetails' => $activationInfo
                ]
            ];
            
        } catch (\Exception | ExceptionInterface $e) {
            $errorMessage = 'Erreur lors de la récupération des informations d\'activation : ' . $e->getMessage();
            $this->executionLogger->logError(
                'Erreur récupération passcodes',
                'Erreur lors de la récupération des informations d\'activation',
                $e
            );

            try {
                $pieceDetails = $this->pieceDetailsRepository->findByPcdid($command->getPcdid());
                if (!empty($pieceDetails) && isset($pieceDetails[0]['MEMOID'])) {
                    $memoId = $pieceDetails[0]['MEMOID'];
                    
                    $this->commandBus->dispatch(new UpdateMemoAndApiCommand(
                        $command->getPcdid(),
                        $memoId,
                        $command->getUser(),
                        [[
                            'title' => 'Erreur passcodes',
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
                    $memoEx
                );
            }
            
            return [
                'status' => 'error',
                'messages' => [
                    [
                        'type' => 'error',
                        'message' => $errorMessage,
                        'status' => 'error'
                    ]
                ]
            ];
        }
    }

    private function getArtcodeForSerial(string $serialNumber, string $pcdnum): ?string {
        $artCodeParents = $this->pieceDetailsRepository->getArticleParent($pcdnum);
        foreach ($artCodeParents as $parent) {
            if ($parent['PLDDIVERS'] === $serialNumber) {
                return $parent['ARTCODE'];
            }
        }
        return null;
    }
}
