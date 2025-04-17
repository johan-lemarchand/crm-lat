<?php

namespace App\ODF\Domain\Repository;

interface WavesoftConnectionInterface
{
    public function executeQuery(string $sql, array $params = []): \Doctrine\DBAL\Result;
    public function executeStatement(string $sql, array $params = []): int;
    public function getDatabase(): ?string;
}
