<?php

namespace App\ODF\Infrastructure\Service;

use App\ODF\Domain\Service\TcpServiceInterface;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class TcpService implements TcpServiceInterface
{
    public function __construct(
        #[Autowire('%tcp.host%')] private string $host,
        #[Autowire('%tcp.port%')] private int $port,
        #[Autowire('%tcp.instance_sql%')] private string $instanceSql,
        #[Autowire('%tcp.dossier%')] private string $dossier,
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection,
    ) {
        // Validation des paramètres au démarrage
        if (!$this->port) {
            throw new \InvalidArgumentException('Le port TCP n\'est pas configuré correctement');
        }
        if (!$this->instanceSql) {
            throw new \InvalidArgumentException('L\'instance SQL n\'est pas configurée correctement');
        }
        if (!$this->dossier) {
            throw new \InvalidArgumentException('Le dossier n\'est pas configuré correctement');
        }
    }

    /**
     * @throws Exception
     */
    public function sendWaveSoftCommand(string $data): string|false
    {
        try {
            $socket = $this->createAndConnectSocket();

            // Si c'est un ID de trame (numérique), on utilise l'ancien format
            if (is_numeric($data)) {
                $command = $this->formatTrameCommand((int)$data);
            } else {
                $command = iconv('UTF-8', 'Windows-1252', $data . "\r\n");
            }

            socket_write($socket, $command, strlen($command));
            $response = socket_read($socket, 2048);
            socket_close($socket);

            return $response;

        } catch (Exception $e) {
            restore_error_handler();
            
            // Si c'est un ID de trame, on met à jour le statut
            if (is_numeric($data)) {
                $this->changeStatusAutomate((int)$data);
            }

            throw $e;
        }
    }

    /**
     * Crée et connecte un socket
     * @throws Exception
     */
    private function createAndConnectSocket()
    {
        set_error_handler(function($errno, $errstr) {
            throw new Exception("Erreur de connexion WaveSoft: $errstr, Contactez le service informatique.");
        });

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new Exception("Contactez le service informatique. Erreur lors de la création du socket WaveSoft: " . socket_strerror(socket_last_error()));
        }

        $result = socket_connect($socket, $this->host, $this->port);

        if ($result === false) {
            $error = socket_strerror(socket_last_error($socket));
            socket_close($socket);
            throw new Exception("Erreur de connexion WaveSoft: Impossible de se connecter à {$this->host}:{$this->port}, Contactez le service informatique.");
        }

        restore_error_handler();
        return $socket;
    }

    /**
     * Formate la commande pour une trame
     */
    private function formatTrameCommand(int $idTrame): string
    {
        return iconv('UTF-8', 'UTF-16LE',
            "{$idTrame};EXECUTE;AUTOMATE;{$this->instanceSql};{$this->dossier};"
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function changeStatusAutomate(int $trsid): void
    {
        $this->connection->executeStatement(
            "UPDATE WSAUTOMATE SET TRSETAT = 'E' WHERE TRSID = :trsid",
            ['trsid' => $trsid]
        );
    }
}
