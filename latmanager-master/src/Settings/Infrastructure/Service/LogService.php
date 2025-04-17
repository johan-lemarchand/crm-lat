<?php

namespace App\Settings\Infrastructure\Service;

use App\Settings\Domain\Repository\LogRepositoryInterface;
use App\Settings\Domain\Service\LogServiceInterface;
use Psr\Log\LoggerInterface;

readonly class LogService implements LogServiceInterface
{
    private const PHP_LOG_FILES = [
        'C:/xampp/php/logs/php_error_log',
        'C:/xampp/php/logs/error.log',
    ];

    private const APACHE_LOG_FILES = [
        'C:/xampp/apache/logs/error.log',
        'C:/xampp/apache/logs/access.log',
    ];

    public function __construct(
        private LogRepositoryInterface $logRepository,
        private LoggerInterface $logger
    ) {}

    public function getPhpLogs(): array
    {
        $logs = '';
        $totalSize = 0;

        foreach (self::PHP_LOG_FILES as $logFile) {
            if (file_exists($logFile)) {
                $content = file_get_contents($logFile) ?: '';
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
                $logs .= $content;
                $totalSize += filesize($logFile);
            }
        }

        return [
            'content' => $logs,
            'size' => $totalSize,
        ];
    }

    public function getApacheLogs(): array
    {
        $logs = '';
        $totalSize = 0;

        foreach (self::APACHE_LOG_FILES as $logFile) {
            if (file_exists($logFile)) {
                $content = file_get_contents($logFile) ?: '';
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
                $logs .= $content;
                $totalSize += filesize($logFile);
            }
        }

        return [
            'content' => $logs,
            'size' => $totalSize,
        ];
    }

    public function getDbStats(): array
    {
        try {
            $stats = $this->logRepository->getDbStats();
            return [
                'total_size' => $stats['total_size'],
                'total_logs' => $stats['total_logs'],
                'commands' => array_map(function ($command) {
                    return [
                        'id' => $command['id'],
                        'name' => $command['name'],
                        'scriptName' => $command['script_name'],
                        'executionCount' => (int) $command['execution_count'],
                        'apiLogsCount' => (int) $command['api_logs_count'],
                        'resumeCount' => (int) $command['resume_count'],
                        'size' => $this->formatSize($command['total_size']),
                        'details' => [
                            'execution' => $this->formatSize($command['execution_size']),
                            'api' => $this->formatSize($command['api_size']),
                            'resume' => $this->formatSize($command['resume_size']),
                        ],
                    ];
                }, $stats['commands']),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du calcul des statistiques de la base de données', [
                'error' => $e->getMessage(),
            ]);

            return [
                'total_size' => 0,
                'total_logs' => 0,
                'commands' => [],
            ];
        }
    }

    public function clearLogs(string $type, ?string $commandId = null, ?string $logType = null): void
    {
        try {
            switch ($type) {
                case 'php':
                    $this->clearPhpLogs();
                    break;
                case 'apache':
                    $this->clearApacheLogs();
                    break;
                case 'db':
                    $this->logRepository->clearDbLogs($commandId, $logType);
                    break;
                default:
                    throw new \InvalidArgumentException('Type de log invalide');
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression des logs', [
                'error' => $e->getMessage(),
                'type' => $type,
                'commandId' => $commandId,
                'logType' => $logType,
            ]);
            throw $e;
        }
    }

    public function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    private function clearPhpLogs(): void
    {
        foreach (self::PHP_LOG_FILES as $logFile) {
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
            }
        }
    }

    private function clearApacheLogs(): void
    {
        foreach (self::APACHE_LOG_FILES as $logFile) {
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
            }
        }
    }
} 