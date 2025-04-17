<?php

namespace App\Repository;

use App\Entity\WavesoftLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WavesoftLog>
 *
 * @method WavesoftLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method WavesoftLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method WavesoftLog[]    findAll()
 * @method WavesoftLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WavesoftLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WavesoftLog::class);
    }
}
