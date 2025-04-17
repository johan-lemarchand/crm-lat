<?php

namespace App\ODF\Infrastructure\Repository;

use App\Entity\OdfLog;
use App\ODF\Domain\Repository\OdfLogRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OdfLogRepository extends ServiceEntityRepository implements OdfLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OdfLog::class);
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?OdfLog
    {
        return parent::findOneBy($criteria, $orderBy);
    }

    public function save(OdfLog $odfLog, bool $flush = false): void
    {
        $this->getEntityManager()->persist($odfLog);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOrCreate(string $pcdnum): OdfLog
    {
        $odfLog = $this->findOneBy(['name' => $pcdnum]);

        if (!$odfLog) {
            $odfLog = new OdfLog();
            $odfLog->setName($pcdnum);
            $odfLog->setStatus('En cours');
            $odfLog->setExecutionTime(0);
            $this->save($odfLog, true);
        }

        return $odfLog;
    }

    public function findLatestWithExecutions(int $limit = 10): array
    {
        return $this->createQueryBuilder('ol')
            ->leftJoin('ol.executions', 'e')
            ->addSelect('e')
            ->orderBy('ol.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('ol')
            ->leftJoin('ol.executions', 'e')
            ->addSelect('e')
            ->andWhere('ol.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('ol.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
} 