<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Repository\CouponRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Tests\Model;

readonly class DbalCouponRepository implements CouponRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection
    ) {}

    /**
     * @throws Exception
     */
    public function findAvailableCoupons(int $artId, int $quantity, array $lockedSerials = []): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('o.OPEID', 'o.OPENUMSERIE', 'v.QTELIVRABLE', 'o.OPELASTPA', 'o.OPEPMP', 'o.OPECUMP')
            ->from('OPERATIONSTOCK'/** @type MODEL */, 'o')
            ->innerJoin('o', 'ARTSERIE'/** @type MODEL */, 'a', 'o.ARTID = a.ARTID AND o.OPENUMSERIE = a.ATSNUMSERIE')
            ->innerJoin('o', 'V_STOCK_LOTSERIE_LOCKED'/** @type MODEL */, 'v', 'v.ARTID = :artid AND v.DEPID = 1 AND v.OPENUMSERIE = o.OPENUMSERIE')
            ->where('o.ARTID = :artid')
            ->andWhere('o.DEPID = 1')
            ->andWhere('o.OPEISCLOS = \'N\'')
            ->andWhere('o.OPENATURESTOCK = \'R\'')
            ->andWhere('o.OPEQTERESTANTE > 0')
            ->andWhere('a.ATSSTATUT = \'D\'')
            ->andWhere('v.QTELIVRABLE > 0')
            ->setParameter('artid', $artId);

        if (!empty($lockedSerials)) {
            $qb->andWhere('o.OPENUMSERIE NOT IN (:serials)')
                ->setParameter('serials', $lockedSerials, Connection::PARAM_STR_ARRAY);
        }

        $qb->orderBy('o.OPEDATE', 'ASC')
            ->addOrderBy('o.OPEID', 'ASC')
            ->setMaxResults($quantity);

        return $qb->executeQuery()->fetchAllAssociative();
    }
} 