<?php

namespace App\ABO\Infrastructure\Repository;

use App\ABO\Domain\Interface\UpdateAboRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class DbalUpdateAboRepository implements UpdateAboRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection
    ) {}

    /**
     * @throws \Exception
     */
    public function updateTIRID(string $tirId, string $pcvnum): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE PIECEVENTES SET TIRID = ? WHERE PCVNUM = ?',
                [
                    $tirId,
                    $pcvnum
                ]
            );
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la mise à jour des informations client: ' . $e->getMessage());
        }
    }
} 