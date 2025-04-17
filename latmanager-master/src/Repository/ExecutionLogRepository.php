<?php

namespace App\Repository;

use App\Entity\ExecutionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExecutionLog>
 */
class ExecutionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExecutionLog::class);
    }

    /**
     * Récupère les derniers logs d'exécution
     *
     * @param int $limit Nombre de logs à récupérer
     * @return array<ExecutionLog>
     */
    public function findLatest(int $limit = 20): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.startDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les logs d'exécution avec un statut spécifique
     *
     * @param string $status Statut des logs à récupérer
     * @param int $limit Nombre de logs à récupérer
     * @return array<ExecutionLog>
     */
    public function findByStatus(string $status, int $limit = 20): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.status = :status')
            ->setParameter('status', $status)
            ->orderBy('e.startDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les logs d'exécution pour une commande spécifique
     *
     * @param string $commandName Nom de la commande
     * @param int $limit Nombre de logs à récupérer
     * @return array<ExecutionLog>
     */
    public function findByCommand(string $commandName, int $limit = 20): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.commandName = :commandName')
            ->setParameter('commandName', $commandName)
            ->orderBy('e.startDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les logs d'exécution pour une période donnée
     *
     * @param \DateTime $startDate Date de début
     * @param \DateTime $endDate Date de fin
     * @return array<ExecutionLog>
     */
    public function findByPeriod(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.startDate >= :startDate')
            ->andWhere('e.startDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('e.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
} 