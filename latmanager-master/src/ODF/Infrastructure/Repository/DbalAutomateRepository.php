<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Repository\AutomateRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class DbalAutomateRepository implements AutomateRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection
    ) {}

    /**
     * @throws Exception
     */
    public function deleteAutomate(string $pcdnum): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->delete('AUTOMATE')
            ->where('PCDNUM = :pcdnum')
            ->setParameter('pcdnum', $pcdnum)
            ->executeStatement();
    }
} 