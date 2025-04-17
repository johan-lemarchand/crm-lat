<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Repository\WavesoftConnectionInterface;
use Doctrine\DBAL\Connection;

class WavesoftConnection implements WavesoftConnectionInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    public function executeQuery(string $sql, array $params = []): \Doctrine\DBAL\Result
    {
        return $this->connection->executeQuery($sql, $params);
    }

    public function executeStatement(string $sql, array $params = []): int
    {
        return $this->connection->executeStatement($sql, $params);
    }

    public function getDatabase(): ?string
    {
        return $this->connection->getDatabase();
    }
}
