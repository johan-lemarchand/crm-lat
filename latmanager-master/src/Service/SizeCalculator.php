<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class SizeCalculator
{
    public function __construct(
        private Connection $connection
    ) {}

    /**
     * @throws Exception
     */
    public function getExecutionSize(int $commandId): int
    {
        $sql = '
            SELECT COALESCE(SUM(CAST(DATALENGTH(ce.output) as bigint)), 0) + 
                COALESCE(SUM(CAST(DATALENGTH(ce.error) as bigint)), 0) as size_value
            FROM dbo.commandExecution ce 
            WHERE ce.command_id = :command
        ';
        
        $result = $this->connection->executeQuery($sql, ['command' => $commandId])->fetchOne();
        return (int)$result;
    }

    /**
     * @throws Exception
     */
    public function getApiLogsSize(int $commandId): int
    {
        $sql = '
            SELECT COALESCE(SUM(CAST(DATALENGTH(l.requestXml) as bigint)), 0) + 
                COALESCE(SUM(CAST(DATALENGTH(l.responseXml) as bigint)), 0) as size_value
            FROM dbo.praxedoApiLog l 
            JOIN dbo.commandExecution e ON l.execution_id = e.id
            WHERE e.command_id = :command
        ';
        
        $result = $this->connection->executeQuery($sql, ['command' => $commandId])->fetchOne();
        return (int)$result;
    }

    /**
     * @throws Exception
     */
    public function getResumeSize(int $commandId): int
    {
        $sql = '
            SELECT COALESCE(SUM(CAST(DATALENGTH(r.resume) as bigint)), 0) as size_value
            FROM dbo.logResume r 
            WHERE r.command_id = :command
        ';
        
        $result = $this->connection->executeQuery($sql, ['command' => $commandId])->fetchOne();
        return (int)$result;
    }
} 