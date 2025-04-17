<?php

namespace App\ODF\Infrastructure\Repository;

use App\Entity\OdfExecution;
use App\Entity\OdfLog;
use App\ODF\Domain\Repository\OdfExecutionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OdfExecution>
 */
class OdfExecutionRepository extends ServiceEntityRepository implements OdfExecutionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OdfExecution::class);
    }

    public function save(OdfExecution $execution, bool $flush = false): void
    {
        $this->getEntityManager()->persist($execution);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    
    public function findLastExecutionForLog(OdfLog $odfLog): ?OdfExecution
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.odfLog = :odfLog')
            ->setParameter('odfLog', $odfLog)
            ->orderBy('e.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
} 