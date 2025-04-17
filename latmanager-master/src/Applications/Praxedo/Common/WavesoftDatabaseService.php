<?php

namespace App\Applications\Praxedo\Common;

class WavesoftDatabaseService
{
    private ?\PDO $connection = null;

    public function __construct()
    {
        $this->connect();
    }

    private function connect(): void
    {
        try {
            if (!isset($_ENV['DATABASE_WAVESOFT'])) {
                throw new PraxedoException('La variable d\'environnement DATABASE_WAVESOFT n\'est pas définie');
            }

            if (!extension_loaded('pdo_sqlsrv')) {
                throw new PraxedoException('L\'extension PHP pdo_sqlsrv n\'est pas installée');
            }

            $connectionString = $_ENV['DATABASE_WAVESOFT'];
            preg_match('/jdbc:sqlserver:\/\/(.+);instanceName=(.+);databaseName=(.+);user=(.+);password=(.+)/', $connectionString, $matches);

            if (6 !== count($matches)) {
                throw new PraxedoException('Format de chaîne de connexion invalide');
            }

            $dsn = sprintf(
                'sqlsrv:Server=%s\%s;Database=%s;TrustServerCertificate=1;Encrypt=0',
                $matches[1],
                $matches[2],
                $matches[3]
            );

            $this->connection = new \PDO(
                $dsn,
                $matches[4],
                $matches[5],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::SQLSRV_ATTR_ENCODING => \PDO::SQLSRV_ENCODING_UTF8,
                ]
            );
        } catch (\PDOException $e) {
            throw new PraxedoException('Erreur de connexion à la base de données WAVESOFT: '.$e->getMessage());
        }
    }

    public function getConnection(): \PDO
    {
        if (null === $this->connection) {
            $this->connect();
        }

        return $this->connection;
    }

    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new PraxedoException('Erreur lors de l\'exécution de la requête: '.$e->getMessage());
        }
    }
}
