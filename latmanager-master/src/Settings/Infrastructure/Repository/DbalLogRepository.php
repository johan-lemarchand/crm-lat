<?php

namespace App\Settings\Infrastructure\Repository;

use App\Settings\Domain\Repository\LogRepositoryInterface;
use App\Service\SizeCalculator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class DbalLogRepository implements LogRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        private SizeCalculator $sizeCalculator
    ) {}

    /**
     * @throws Exception
     */
    public function getCommandsWithLogs(): array
    {
        return $this->connection->executeQuery('
            SELECT DISTINCT 
                c.id,
                c.name,
                c.script_name,
                COUNT(DISTINCT ce.id) as execution_count,
                COUNT(pal.id) as api_logs_count
            FROM dbo.command c
            LEFT JOIN dbo.commandExecution ce ON ce.command_id = c.id
            LEFT JOIN dbo.praxedoApiLog pal ON pal.execution_id = ce.id
            GROUP BY c.id, c.name, c.script_name
            HAVING execution_count > 0 OR api_logs_count > 0
            ORDER BY c.name ASC, c.script_name ASC
        ')->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function getDbStats(): array
    {
        $commands = $this->connection->executeQuery('
            SELECT 
                c.id,
                c.name,
                c.script_name,
                COUNT(DISTINCT ce.id) as execution_count,
                COUNT(DISTINCT pal.id) as api_logs_count,
                COUNT(DISTINCT lr.id) as resume_count
            FROM dbo.command c
            LEFT JOIN dbo.commandExecution ce ON ce.command_id = c.id
            LEFT JOIN dbo.praxedoApiLog pal ON pal.execution_id = ce.id
            LEFT JOIN dbo.logResume lr ON lr.command_id = c.id
            GROUP BY c.id, c.name, c.script_name
        ')->fetchAllAssociative();

        $totalSize = 0;
        $totalLogs = 0;
        $commandStats = [];

        foreach ($commands as $command) {
            $executionSize = $this->sizeCalculator->getExecutionSize($command['id']);
            $apiSize = $this->sizeCalculator->getApiLogsSize($command['id']);
            $resumeSize = $this->sizeCalculator->getResumeSize($command['id']);
            $totalCommandSize = $executionSize + $apiSize + $resumeSize;

            if ($totalCommandSize > 0) {
                $commandStats[] = array_merge($command, [
                    'execution_size' => $executionSize,
                    'api_size' => $apiSize,
                    'resume_size' => $resumeSize,
                    'total_size' => $totalCommandSize
                ]);

                $totalSize += $totalCommandSize;
                $totalLogs += (int)$command['execution_count'] + (int)$command['api_logs_count'] + (int)$command['resume_count'];
            }
        }

        usort($commandStats, fn($a, $b) => $b['total_size'] <=> $a['total_size']);

        return [
            'total_size' => $totalSize,
            'total_logs' => $totalLogs,
            'commands' => $commandStats,
        ];
    }

    /**
     * @throws Exception
     */
    public function clearDbLogs(?string $commandId = null, ?string $logType = null): void
    {
        $this->connection->beginTransaction();

        try {
            if ($commandId) {
                $this->clearSpecificCommandLogs($commandId, $logType);
            } else {
                $this->clearAllLogs($logType);
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    private function clearSpecificCommandLogs(string $commandId, ?string $logType): void
    {
        switch ($logType) {
            case 'execution':
                $this->connection->executeStatement(
                    'DELETE FROM dbo.commandExecution WHERE command_id = :commandId',
                    ['commandId' => $commandId]
                );
                break;

            case 'api':
                $this->connection->executeStatement('
                    DELETE pal FROM dbo.PraxedoApiLog pal
                    INNER JOIN dbo.commandExecution ce ON ce.id = pal.execution_id
                    WHERE ce.command_id = :commandId
                ', ['commandId' => $commandId]);
                break;

            case 'resume':
                $this->connection->executeStatement(
                    'DELETE FROM dbo.logResume WHERE command_id = :commandId',
                    ['commandId' => $commandId]
                );
                break;

            default:
                $this->clearAllLogsForCommand($commandId);
        }
    }

    /**
     * @throws Exception
     */
    private function clearAllLogsForCommand(string $commandId): void
    {
        $this->connection->executeStatement('
            DELETE pal FROM dbo.PraxedoApiLog pal
            INNER JOIN dbo.commandExecution ce ON ce.id = pal.execution_id
            WHERE ce.command_id = :commandId
        ', ['commandId' => $commandId]);

        $this->connection->executeStatement(
            'DELETE FROM dbo.logResume WHERE command_id = :commandId',
            ['commandId' => $commandId]
        );

        $this->connection->executeStatement(
            'DELETE FROM dbo.commandExecution WHERE command_id = :commandId',
            ['commandId' => $commandId]
        );
    }

    /**
     * @throws Exception
     */
    private function clearAllLogs(?string $logType): void
    {
        switch ($logType) {
            case 'execution':
                $this->connection->executeStatement('TRUNCATE TABLE dbo.commandExecution');
                break;

            case 'api':
                $this->connection->executeStatement('TRUNCATE TABLE dbo.praxedoApiLog');
                break;

            case 'resume':
                $this->connection->executeStatement('TRUNCATE TABLE dbo.logResume');
                break;

            default:
                $this->connection->executeStatement('TRUNCATE TABLE dbo.praxedoApiLog');
                $this->connection->executeStatement('TRUNCATE TABLE dbo.logResume');
                $this->connection->executeStatement('TRUNCATE TABLE dbo.commandExecution');
        }
    }
} 