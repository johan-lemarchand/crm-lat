<?php

namespace App\ODF\Application\Command\CloseOdf;

use App\ODF\Domain\Service\ManufacturingService;
use App\ODF\Domain\Service\EventService;
use App\ODF\Infrastructure\Service\Timer;
use App\ODF\Infrastructure\Service\MemoAndApiService;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;

readonly class CloseOdfHandler
{
    public function __construct(
        private ManufacturingService $manufacturingService,
        private Timer                $timer,
        private EventService         $eventService,
        private MemoAndApiService    $memoAndApiService,
        private OdfExecutionLogger   $executionLogger
    ) {}

    public function __invoke(CloseOdfCommand $command): array
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'CloseOdfHandler',
            [
                'pcdnum' => $command->getPcdnum(),
                'pcdid' => $command->getPcdid(),
                'orderNumber' => $command->getOrderNumber()
            ]
        );

        try {
            $this->executionLogger->addStep(
                'Début clôture',
                'info',
                sprintf('Clôture de l\'ODF %s', $command->getPcdnum())
            );

            $result = $this->manufacturingService->closeOdf($command->getPcdnum());
            
            $this->executionLogger->addStep(
                'Clôture ODF',
                $result ? 'success' : 'error',
                $result ? 'ODF clôturé avec succès' : 'Échec de la clôture'
            );
            
            if (!$result) {
                $this->executionLogger->finishHandler('CloseOdfHandler', $handlerStartTime, [
                    'status' => 'error',
                    'error' => 'Échec de la clôture de l\'ODF'
                ]);
                return $this->handleError('Échec de la clôture de l\'ODF');
            }

            $this->updateMemoAndNotify(
                $command->getMemoId(),
                $command->getUser(),
                $command->getPcdid(),
                $command->getOrderNumber()
            );

            $this->executionLogger->finishHandler('CloseOdfHandler', $handlerStartTime, [
                'status' => 'success',
                'orderNumber' => $command->getOrderNumber()
            ]);

            return [
                'status' => 'success',
                'message' => 'Clôture de l\'ODF effectuée avec succès'
            ];

        } catch (\Exception $e) {
            $this->executionLogger->logError(
                'Erreur clôture',
                'Erreur lors de la clôture de l\'ODF',
                $e
            );
            return $this->handleError($e->getMessage());
        }
    }

    private function handleError(string $message): array
    {
        $this->executionLogger->addStep(
            'Erreur clôture',
            'error',
            $message
        );

        $this->eventService->sendEvent('message', [
            'status' => 'error',
            'message' => $message,
            'section' => 'bdfa',
            'type' => 'end'
        ]);

        return [
            'status' => 'error',
            'message' => $message
        ];
    }

    /**
     * @throws \Exception
     */
    private function updateMemoAndNotify(int $memoId, string $user, int $pcdid, string $orderNumber): void
    {
        $successMessage = 'Clôture de l\'ODF effectuée avec succès';

        $memoMessages = [[
            'title' => 'Clôture ODF',
            'content' => $successMessage,
            'status' => 'success'
        ]];

        $this->memoAndApiService->updateMemo($memoId, $memoMessages, $user);
        $this->memoAndApiService->updatePieceDiversApi($pcdid, [
            'status' => 'success',
            'orderNumber' => $orderNumber,
            'odfClosed' => true
        ]);

        $this->executionLogger->addStep(
            'Mise à jour mémo',
            'success',
            'Mémo et API mis à jour avec succès'
        );

        $this->eventService->sendEvent('message', [
            'status' => 'success',
            'message' => $successMessage,
            'section' => 'bdfa',
            'type' => 'end'
        ]);
    }
}
