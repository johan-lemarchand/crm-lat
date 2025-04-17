<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Repository\PieceDiversRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class PieceDiversRepository implements PieceDiversRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection
    ) {}

    /**
     * @throws Exception
     */
    public function updateStatus(int $pcdid, string $user, array $data): void
    {
        $statut = $data['status'] === 'error' ? 'ERROR' : 'SUCCESS';
        $dateApi = date('Ymd H:i:s');
        
        $this->connection->executeStatement(
            'UPDATE PIECEDIVERS_P SET 
            STATUTAPI = ?, 
            DATEAPI = ?, 
            USERAPI = ?
            WHERE PCDID = ?',
            [
                $statut,
                $dateApi,
                $user,
                $pcdid
            ]
        );
    }
} 