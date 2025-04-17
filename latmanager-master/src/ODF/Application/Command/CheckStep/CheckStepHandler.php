<?php

namespace App\ODF\Application\Command\CheckStep;

use App\ODF\Domain\Service\UniqueIdServiceInterface;
use App\ODF\Domain\Repository\PieceDetailsRepositoryInterface;
use App\ODF\Domain\Service\AutomateServiceInterface;
use App\ODF\Infrastructure\Service\Timer;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use App\ODF\Application\Command\CheckArticles\CheckArticlesCommand;
use App\ODF\Application\Command\CheckAffaire\CheckAffaireCommand;
use React\Promise\Promise;

#[AsMessageHandler]
class CheckStepHandler
{
    public function __construct(
        private readonly UniqueIdServiceInterface $uniqueIdService,
        private readonly PieceDetailsRepositoryInterface $pieceDetailsRepository,
        private readonly AutomateServiceInterface $automateService,
        private readonly Timer $timer,
        private readonly MessageBusInterface $commandBus,
        private readonly OdfExecutionLogger $executionLogger
    ) {}

    public function __invoke(CheckStepCommand $command): array
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'CheckStepHandler',
            [
                'step' => $command->getStep(),
                'pcdid' => $command->getPcdid(),
                'pcdnum' => $command->getPcdnum()
            ]
        );

        $step = $command->getStep();
        $pcdid = $command->getPcdid();
        $pcdnum = $command->getPcdnum();

        try {
            switch ($step) {
                case 'Initialisation':
                    $this->executionLogger->addStep(
                        'Début vérifications',
                        'info',
                        sprintf('Démarrage des vérifications pour la pièce %s', $pcdid)
                    );
                    $response['progress'] = 20;
                    
                    $uniqueIdResult = $this->uniqueIdService->checkCloseOdfAndUniqueId($pcdid);
                    $this->executionLogger->addStep(
                        'Vérification ID unique',
                        $uniqueIdResult['status'] ?? 'error',
                        $uniqueIdResult['messages'][0]['message'] ?? 'Erreur lors de la vérification'
                    );

                    if (!$uniqueIdResult || $uniqueIdResult['status'] === 'error') {
                        $this->executionLogger->finishHandler('CheckStepHandler', $handlerStartTime, [
                            'status' => 'error',
                            'step' => $step,
                            'error' => $uniqueIdResult['messages'][0]['message'] ?? 'Erreur lors de la vérification'
                        ]);
                        return [
                            'status' => 'error',
                            'progress' => 20,
                            'messages' => $uniqueIdResult['messages'] ?? [['type' => 'error', 'message' => 'Erreur lors de la vérification']]
                        ];
                    }
                    break;

                case 'Vérification des articles':
                    $response['progress'] = 40;
                    $pieceDetails = $this->pieceDetailsRepository->findByPcdid($pcdid);
                    
                    if (empty($pieceDetails)) {
                        $this->executionLogger->addStep(
                            'Vérification articles',
                            'error',
                            'Aucun détail trouvé pour cette pièce'
                        );
                        $this->executionLogger->finishHandler('CheckStepHandler', $handlerStartTime, [
                            'status' => 'error',
                            'step' => $step,
                            'error' => 'Aucun détail trouvé'
                        ]);
                        return $this->errorResponse($response, [[
                            'type' => 'error',
                            'message' => 'Aucun détail trouvé pour cette pièce'
                        ]]);
                    }

                    $envelope = $this->commandBus->dispatch(new CheckArticlesCommand($pieceDetails));
                    $articleCheckResult = $envelope->last(HandledStamp::class)->getResult();
                    
                    $this->executionLogger->addStep(
                        'Vérification articles',
                        $articleCheckResult['status'],
                        $articleCheckResult['status'] === 'error' ? 
                            json_encode($articleCheckResult['messages']) : 
                            'Articles validés avec succès'
                    );

                    if ($articleCheckResult['status'] === 'error') {
                        $this->executionLogger->finishHandler('CheckStepHandler', $handlerStartTime, [
                            'status' => 'error',
                            'step' => $step,
                            'error' => json_encode($articleCheckResult['messages'])
                        ]);
                        return $this->errorResponse($response, $articleCheckResult['messages']);
                    }
                    break;

                case 'Vérification de l\'affaire':
                    $response['progress'] = 60;
                    $envelope = $this->commandBus->dispatch(new CheckAffaireCommand($pcdid));
                    $affaireResult = $envelope->last(HandledStamp::class)->getResult();
                    
                    $this->executionLogger->addStep(
                        'Vérification affaire',
                        $affaireResult['status'],
                        $affaireResult['status'] === 'error' ? 
                            json_encode($affaireResult['messages']) : 
                            'Affaire validée avec succès'
                    );

                    if ($affaireResult['status'] === 'error') {
                        $this->executionLogger->finishHandler('CheckStepHandler', $handlerStartTime, [
                            'status' => 'error',
                            'step' => $step,
                            'error' => json_encode($affaireResult['messages'])
                        ]);
                        return $this->errorResponse($response, $affaireResult['messages']);
                    }
                    break;

                case 'Vérification des numéros de série':
                    $response['progress'] = 80;
                    $pieceDetails = $this->pieceDetailsRepository->findByPcdid($pcdid);
                    
                    $promises = [];
                    foreach ($pieceDetails as $detail) {
                        if (!empty($detail['serie'])) {
                            $this->executionLogger->addStep(
                                'Vérification série',
                                'info',
                                sprintf('Vérification du numéro %s', $detail['serie'])
                            );
                            $promises[] = $this->automateService->checkSerialNumber($detail['serie']);
                        }
                    }
                    
                    $results = Promise\all($promises)->wait();
                    break;

                case 'Vérification des coupons':
                    $response['progress'] = 100;
                    $pieceDetails = $this->pieceDetailsRepository->findByPcdid($pcdid);
                    
                    $promises = [];
                    foreach ($pieceDetails as $detail) {
                        if (!empty($detail['coupon'])) {
                            $this->executionLogger->addStep(
                                'Vérification coupon',
                                'info',
                                sprintf('Vérification du coupon %s', $detail['coupon'])
                            );
                            $promises[] = $this->automateService->checkCoupon($detail['coupon']);
                        }
                    }
                    
                    $results = Promise\all($promises)->wait();

                    $this->executionLogger->addStep(
                        'Validation terminée',
                        'success',
                        'Toutes les vérifications sont OK'
                    );
                    break;

                default:
                    $this->executionLogger->addStep(
                        'Étape inconnue',
                        'error',
                        sprintf('Étape de vérification inconnue: %s', $step)
                    );
                    $this->executionLogger->finishHandler('CheckStepHandler', $handlerStartTime, [
                        'status' => 'error',
                        'step' => $step,
                        'error' => 'Étape inconnue'
                    ]);
                    return $this->errorResponse($response, [[
                        'type' => 'error',
                        'message' => 'Étape de vérification inconnue'
                    ]]);
            }

            $this->executionLogger->finishHandler('CheckStepHandler', $handlerStartTime, [
                'status' => 'success',
                'step' => $step,
                'progress' => $this->getProgressForStep($step)
            ]);

            return [
                'status' => 'success',
                'progress' => $this->getProgressForStep($step),
                'messages' => []
            ];
        } catch (\Exception $e) {
            $this->executionLogger->logError(
                'Erreur vérification',
                sprintf('Erreur lors de la vérification de l\'étape %s', $step),
                $e
            );
            return [
                'status' => 'error',
                'progress' => $this->getProgressForStep($step),
                'messages' => [[
                    'type' => 'error',
                    'message' => $e->getMessage()
                ]]
            ];
        }
    }

    private function getProgressForStep(string $step): int
    {
        return match ($step) {
            'Initialisation' => 20,
            'Vérification des articles' => 40,
            'Vérification de l\'affaire' => 60,
            'Vérification des numéros de série' => 80,
            'Vérification des coupons' => 100,
            default => 0
        };
    }

    private function errorResponse(array $response, ?array $messages = null): array
    {
        return [
            'status' => 'error',
            'currentStep' => $response['currentStep'] ?? null,
            'progress' => $response['progress'],
            'messages' => $messages ?? [['type' => 'error', 'message' => 'Une erreur est survenue']]
        ];
    }
} 