<?php

namespace App\Command\Application\Command\DeleteExecutionLogs;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Command\Domain\Repository\CommandExecutionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeleteExecutionLogsHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private CommandExecutionRepositoryInterface $executionRepository
    ) {}

    public function __invoke(DeleteExecutionLogsCommand $command): array
    {
        $scriptCommand = $this->commandRepository->find($command->getCommandId());
        
        if (!$scriptCommand) {
            throw new \RuntimeException('Command not found');
        }

        $this->executionRepository->deleteExecutionLogs(
            $scriptCommand,
            $command->getStartDate(),
            $command->getEndDate(),
            $command->getLastExecutionId()
        );

        return ['message' => 'Logs d\'exécution supprimés avec succès'];
    }
} 