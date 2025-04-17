<?php

namespace App\Repository;

use App\Entity\PraxedoApiLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PraxedoApiLog>
 *
 * @method PraxedoApiLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method PraxedoApiLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method PraxedoApiLog[]    findAll()
 * @method PraxedoApiLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PraxedoApiLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PraxedoApiLog::class);
    }
}
