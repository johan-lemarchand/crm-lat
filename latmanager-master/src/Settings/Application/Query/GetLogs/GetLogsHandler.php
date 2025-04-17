<?php

namespace App\Settings\Application\Query\GetLogs;

use App\Settings\Domain\Service\LogServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetLogsHandler
{
    public function __construct(
        private LogServiceInterface $logService
    ) {}

    public function __invoke(GetLogsQuery $query): array
    {
        $phpLogs = $this->logService->getPhpLogs();
        $apacheLogs = $this->logService->getApacheLogs();
        $dbStats = $this->logService->getDbStats();

        return [
            'php' => [
                'content' => $phpLogs['content'],
                'size' => $this->logService->formatSize($phpLogs['size']),
            ],
            'apache' => [
                'content' => $apacheLogs['content'],
                'size' => $this->logService->formatSize($apacheLogs['size']),
            ],
            'db' => [
                'size' => $this->logService->formatSize($dbStats['total_size']),
                'total_logs' => $dbStats['total_logs'],
                'commands' => $dbStats['commands'],
            ],
        ];
    }
} 