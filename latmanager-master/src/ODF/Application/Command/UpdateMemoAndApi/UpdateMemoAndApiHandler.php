<?php

namespace App\ODF\Application\Command\UpdateMemoAndApi;

use App\ODF\Infrastructure\Service\MemoAndApiService;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use Doctrine\DBAL\Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UpdateMemoAndApiHandler
{
    public function __construct(
        private MemoAndApiService $memoAndApiService,
        private OdfExecutionLogger $executionLogger
    ) {}

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function __invoke(UpdateMemoAndApiCommand $command): void
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'UpdateMemoAndApiHandler',
            [
                'pcdid' => $command->getPcdid(),
                'memoId' => $command->getMemoId()
            ]
        );

        try {
            // Mise à jour du mémo
            $this->executionLogger->addStep(
                'Mise à jour mémo',
                'info',
                sprintf('Mise à jour du mémo %s avec %d messages', 
                    $command->getMemoId(), 
                    count($command->getMessages())
                )
            );

            $this->memoAndApiService->updateMemo(
                $command->getMemoId(),
                $command->getMessages(),
                $command->getUser()
            );

            // Mise à jour des infos API
            $this->executionLogger->addStep(
                'Mise à jour API',
                'info',
                sprintf('Mise à jour des infos API pour PCDID %s', $command->getPcdid())
            );

            $this->memoAndApiService->updatePieceDiversApi(
                $command->getPcdid(),
                $command->getUser(),
                $command->getApiData()
            );

            $this->executionLogger->finishHandler('UpdateMemoAndApiHandler', $handlerStartTime, [
                'status' => 'success',
                'memoId' => $command->getMemoId(),
                'pcdid' => $command->getPcdid()
            ]);

        } catch (\Exception $e) {
            $this->executionLogger->logError(
                'Erreur mise à jour',
                'Erreur lors de la mise à jour du mémo et des infos API',
                $e
            );
            throw $e;
        }
    }
} 