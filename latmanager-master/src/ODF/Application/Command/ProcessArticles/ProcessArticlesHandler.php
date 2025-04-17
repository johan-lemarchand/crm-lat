<?php

namespace App\ODF\Application\Command\ProcessArticles;

use App\ODF\Domain\Service\ArticleProcessServiceInterface;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ProcessArticlesHandler
{
    public function __construct(
        private ArticleProcessServiceInterface $articleProcessService,
        private OdfExecutionLogger $executionLogger
    ) {}

    public function __invoke(ProcessArticlesCommand $command): array
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'ProcessArticlesHandler',
            ['pieceDetails' => count($command->getPiecesDetails())]
        );

        try {
            $result = $this->articleProcessService->processArticlesAndCoupons($command->getPiecesDetails());
            
            $this->executionLogger->addStep(
                'Traitement des articles',
                $result['status'] ?? 'error',
                sprintf(
                    'Traitement terminé avec %d articles traités',
                    count($command->getPiecesDetails())
                )
            );

            $this->executionLogger->finishHandler('ProcessArticlesHandler', $handlerStartTime, [
                'status' => $result['status'] ?? 'error',
                'processedArticles' => count($command->getPiecesDetails())
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->executionLogger->logError(
                'Erreur traitement articles',
                'Erreur lors du traitement des articles',
                $e
            );

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
