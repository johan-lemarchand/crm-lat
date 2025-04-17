<?php

namespace App\Command\Application\Command\ClearApiLogs;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Command\Domain\Repository\CommandExecutionRepositoryInterface;
use App\Command\Domain\Repository\ApiLogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ClearApiLogsHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private CommandExecutionRepositoryInterface $executionRepository,
        private ApiLogRepositoryInterface $apiLogRepository
    ) {}

    public function __invoke(ClearApiLogsCommand $command): array
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

        return ['message' => 'Logs API supprimés avec succès'];
    }
} 