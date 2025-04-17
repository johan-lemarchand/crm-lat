<?php

namespace App\ODF\Application\Command\CheckAffaire;

use App\ODF\Domain\Service\AffaireServiceInterface;
use App\ODF\Infrastructure\Service\Timer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;
use App\ODF\Infrastructure\Service\OdfExecutionLogger;

#[AsMessageHandler]
readonly class CheckAffaireHandler
{
    public function __construct(
        private AffaireServiceInterface $affaireService,
        private LoggerInterface $logger,
        private OdfExecutionLogger $executionLogger
    ) {}

    public function __invoke(CheckAffaireCommand $command): array
    {
        $handlerStartTime = $this->executionLogger->startHandler(
            'CheckAffaireHandler',
            ['pcdid' => $command->getPcdid()]
        );

        try {
            $pcdid = $command->getPcdid();
            $this->executionLogger->addStep(
                'Début vérification affaire',
                'info',
                sprintf('Vérification affaire pour PCDID: %s', $pcdid)
            );

            $result = $this->affaireService->checkAndManageAffaire($pcdid);

            $this->executionLogger->addStep(
                'Résultat vérification',
                $result['status'] ?? 'error',
                isset($result['messages']) ? json_encode($result['messages']) : 'Aucun message'
            );

            $this->executionLogger->finishHandler('CheckAffaireHandler', $handlerStartTime, [
                'status' => $result['status'] ?? 'error'
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->executionLogger->logError(
                'Erreur vérification affaire',
                'Erreur lors de la vérification de l\'affaire',
                $e
            );

            return [
                'status' => 'error',
                'messages' => [[
                    'type' => 'error',
                    'message' => 'Erreur lors de la gestion de l\'affaire : ' . $e->getMessage(),
                    'title' => 'Vérification de l\'affaire'
                ]]
            ];
        }
    }
} 