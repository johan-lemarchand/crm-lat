<?php

namespace App\Repository;

use App\Entity\SyncDate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SyncDate>
 *
 * @method SyncDate|null find($id, $lockMode = null, $lockVersion = null)
 * @method SyncDate|null findOneBy(array $criteria, array $orderBy = null)
 * @method SyncDate[]    findAll()
 * @method SyncDate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SyncDateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncDate::class);
    }

    /**
     * Trouve une entrée par code de synchronisation
     * 
     * @param string $code Code du paramètre de synchronisation
     * @return SyncDate|null
     */
    public function findByCode(string $code): ?SyncDate
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Trouve toutes les entrées par type de synchronisation
     * 
     * @param string $syncType Type de synchronisation
     * @return SyncDate[]
     */
    public function findBySyncType(string $syncType): array
    {
        return $this->findBy(['syncType' => $syncType]);
    }

    /**
     * Sauvegarde une date de synchronisation
     * 
     * @param SyncDate $syncDate Entité SyncDate
     * @return void
     */
    public function save(SyncDate $syncDate): void
    {
        $this->getEntityManager()->persist($syncDate);
        $this->getEntityManager()->flush();
    }

    /**
     * Supprime une date de synchronisation
     * 
     * @param SyncDate $syncDate Entité SyncDate
     * @return void
     */
    public function remove(SyncDate $syncDate): void
    {
        $this->getEntityManager()->remove($syncDate);
        $this->getEntityManager()->flush();
    }

    /**
     * Récupère toutes les dates de synchronisation groupées par type
     * 
     * @return array Dates de synchronisation groupées par type
     */
    public function getAllGroupedByType(): array
    {
        $syncDates = $this->findAll();
        $grouped = [];

        foreach ($syncDates as $syncDate) {
            $type = $syncDate->getSyncType();
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $syncDate;
        }

        return $grouped;
    }
} 