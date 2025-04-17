<?php

namespace App\Applications\Wavesoft;

use Psr\Log\LoggerInterface;

class WavesoftClient
{
    private \PDO $connection;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        $this->initConnection();
    }

    private function initConnection(bool $useTestDb = false): void
    {
        try {
            $startTime = microtime(true);
            
            if ($useTestDb) {
                $dsn = $_ENV['DATABASE_TEST_DSN'];
                $user = $_ENV['DATABASE_TEST_USER'];
                $password = $_ENV['DATABASE_TEST_PASSWORD'];
            } else {
                $dsn = $_ENV['DATABASE_DSN'];
                $user = $_ENV['DATABASE_USER'];
                $password = $_ENV['DATABASE_PASSWORD'];
            }

            $this->connection = new \PDO(
                $dsn,
                $user,
                $password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::SQLSRV_ATTR_ENCODING => \PDO::SQLSRV_ENCODING_UTF8,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
            $this->connection->exec('SET NOCOUNT ON');
            $this->connection->exec('SET ANSI_NULLS ON');
            $this->connection->exec('SET ANSI_WARNINGS ON');

            $this->logger->info('Connexion à Wavesoft établie avec succès', [
                'environment' => $useTestDb ? 'test' : 'production',
                'temps' => round(microtime(true) - $startTime, 3) . 's',
            ]);
        } catch (\PDOException $e) {
            $this->logger->error('Erreur de connexion à Wavesoft', [
                'error' => $e->getMessage(),
                'environment' => $useTestDb ? 'test' : 'production',
                'dsn' => $dsn,
                'username' => $user,
            ]);
            throw $e;
        }
    }

    public function query(string $sql): \PDOStatement
    {
        try {
            return $this->connection->prepare($sql);
        } catch (\PDOException $e) {
            $this->logger->error('Erreur lors de l\'exécution de la requête Wavesoft', [
                'error' => $e->getMessage(),
                'sql' => $sql,
            ]);
            throw $e;
        }
    }

    public function getConnection(): \PDO
    {
        return $this->connection;
    }

    public function useTestDatabase(): void
    {
        $this->initConnection(true);
    }
}
