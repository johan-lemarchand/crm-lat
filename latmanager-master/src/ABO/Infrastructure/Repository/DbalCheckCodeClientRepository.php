<?php

namespace App\ABO\Infrastructure\Repository;

use App\ABO\Domain\Interface\CheckCodeClientRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Tests\Model;

readonly class DbalCheckCodeClientRepository implements CheckCodeClientRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection
    ) {}

    public function findCodeClient(string $codeClient): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
                return $qb
                        ->select('*')
                ->from('TIERS'/** @type MODEL */, 'T')
                ->where('T.TIRCODE = :codeclient')
                ->andWhere("T.TIRISACTIF = 'O'")
                ->andWhere("T.TIRTYPE = 'C'")
                ->setParameter('codeclient', $codeClient)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la récupération du BLC: ' . $e->getMessage());
        }
    }
}
