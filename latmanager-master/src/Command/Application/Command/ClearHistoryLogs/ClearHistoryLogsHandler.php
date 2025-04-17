<?php

namespace App\Command\Application\Command\ClearHistoryLogs;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Command\Domain\Repository\CommandExecutionRepositoryInterface;
use App\Command\Domain\Repository\LogResumeRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ClearHistoryLogsHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private CommandExecutionRepositoryInterface $executionRepository,
        private LogResumeRepositoryInterface $logResumeRepository
    ) {}

    public function __invoke(ClearHistoryLogsCommand $command): array
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

        // Supprimer les résumés de logs
        $this->logResumeRepository->deleteLogResumes(
            $scriptCommand,
            $command->getStartDate(),
            $command->getEndDate(),
            $lastExecution?->getStartedAt()
        );

        return ['message' => 'Historique supprimé avec succès'];
    }
} 