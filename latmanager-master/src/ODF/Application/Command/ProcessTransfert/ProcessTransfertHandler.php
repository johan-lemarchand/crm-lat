<?php

namespace App\ODF\Application\Command\ProcessTransfert;

use App\ODF\Domain\Service\AutomateServiceInterface;
use App\ODF\Infrastructure\Service\Timer;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ProcessTransfertHandler
{
    public function __construct(
        private AutomateServiceInterface $automateService,
        private Timer $timer,
        private OdfExecutionLogger $executionLogger
    ) {}

    public function __invoke(ProcessTransfertCommand $command): array
    {

        $handlerStartTime = $this->executionLogger->startHandler(
            'ProcessTransfertHandler',
            [
                'itemCount' => count($command->getItems()),
                'affaire' => $command->getAffaire()
            ]
        );

        try {
            $this->executionLogger->addStep(
                'Début transfert',
                'info',
                sprintf('Initialisation transfert de %d articles', count($command->getItems()))
            );

            $result = $this->automateService->processTransfertAutomate(
                $command->getItems(),
                $command->getAffaire()
            );

            if (!empty($result)) {
                $this->executionLogger->addStep(
                    'Transfert terminé',
                    'success',
                    sprintf('Transfert réussi de %d articles', count($result))
                );
            } else {
                $this->executionLogger->addStep(
                    'Erreur transfert',
                    'error',
                    'Aucun résultat retourné par l\'automate'
                );
            }

            $this->executionLogger->finishHandler('ProcessTransfertHandler', $handlerStartTime, [
                'status' => !empty($result) ? 'success' : 'error',
                'itemCount' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->executionLogger->logError(
                'Erreur transfert',
                'Erreur lors du transfert vers l\'automate',
                $e
            );

            return [];
        }
    }
}
