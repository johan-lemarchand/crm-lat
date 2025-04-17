<?php

namespace App\Command\Application\Command\ClearAllLogs;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Command\Domain\Repository\CommandExecutionRepositoryInterface;
use App\Command\Domain\Repository\LogResumeRepositoryInterface;
use App\Command\Domain\Repository\ApiLogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ClearAllLogsHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private CommandExecutionRepositoryInterface $executionRepository,
        private LogResumeRepositoryInterface $logResumeRepository,
        private ApiLogRepositoryInterface $apiLogRepository
    ) {}

    public function __invoke(ClearAllLogsCommand $command): array
    {
        $scriptCommand = $this->commandRepository->find($command->getCommandId());
        
        if (!$scriptCommand) {
            throw new \RuntimeException('Command not found');
        }

        // Récupérer la dernière exécution pour la préserver
        $lastExecution = $this->executionRepository->findOneBy(
            ['command' => $scriptCommand],
            ['startedAt' => 'DESC']
        );

        // Supprimer les logs API
        $this->apiLogRepository->deleteApiLogs(
            $scriptCommand,
            $command->getStartDate(),
            $command->getEndDate(),
            $lastExecution?->getId()
        );

        // Supprimer les logs d'exécution
        $this->executionRepository->deleteExecutionLogs(
            $scriptCommand,
            $command->getStartDate(),
            $command->getEndDate(),
            $lastExecution?->getId()
        );

        // Supprimer les résumés de logs
        $this->logResumeRepository->deleteLogResumes(
            $scriptCommand,
            $command->getStartDate(),
            $command->getEndDate()
        );

        return ['message' => 'Historique supprimé avec succès'];
    }
} 