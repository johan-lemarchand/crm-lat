<?php

namespace App\Settings\Application\Query\GetDbCommands;

use App\Settings\Domain\Repository\LogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetDbCommandsHandler
{
    public function __construct(
        private LogRepositoryInterface $logRepository
    ) {}

    public function __invoke(GetDbCommandsQuery $query): array
    {
        $commands = $this->logRepository->getCommandsWithLogs();

        return [
            'commands' => array_map(function ($command) {
                return [
                    'id' => $command['id'],
                    'name' => $command['name'],
                    'scriptName' => $command['script_name'],
                    'executionCount' => (int) $command['execution_count'],
                    'apiLogsCount' => (int) $command['api_logs_count'],
                ];
            }, $commands),
        ];
    }
} 