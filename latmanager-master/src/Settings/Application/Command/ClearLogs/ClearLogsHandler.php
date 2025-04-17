<?php

namespace App\Settings\Application\Command\ClearLogs;

use App\Settings\Domain\Service\LogServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ClearLogsHandler
{
    public function __construct(
        private LogServiceInterface $logService
    ) {}

    public function __invoke(ClearLogsCommand $command): array
    {
        $this->logService->clearLogs(
            $command->getType(),
            $command->getCommandId(),
            $command->getLogType()
        );

        return ['message' => 'Logs vidés avec succès'];
    }
} 