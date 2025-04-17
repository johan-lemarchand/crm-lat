<?php

namespace App\Command\Application\Command\DeleteApiLogs;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Command\Domain\Repository\ApiLogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeleteApiLogsHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private ApiLogRepositoryInterface $apiLogRepository
    ) {}

    public function __invoke(DeleteApiLogsCommand $command): array
    {
        $scriptCommand = $this->commandRepository->find($command->getCommandId());
        
        if (!$scriptCommand) {
            throw new \RuntimeException('Command not found');
        }

        $this->apiLogRepository->deleteApiLogs(
            $scriptCommand,
            $command->getStartDate(),
            $command->getEndDate()
        );

        return ['message' => 'Logs API supprimés avec succès'];
    }
} 