<?php

namespace App\Utils;

use App\Entity\SyncDate;
use App\Repository\SyncDateRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * Gère les dates de synchronisation pour différentes opérations
 */
class SyncDateManager
{
    private const DEFAULT_DAYS_BACK = 1;
    private const PREFIX = 'LAST_SYNC_';

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly SyncDateRepository $syncDateRepository
    ) {
    }

    /**
     * Obtient la dernière date de synchronisation pour un type donné
     * 
     * @param string $syncType Type de synchronisation (ex: CLIENTS_PRAXEDO)
     * @param int $defaultDaysBack Nombre de jours par défaut en cas d'absence de date
     * @return string Date au format Y-m-d H:i:s
     */
    public function getLastSyncDate(string $syncType, int $defaultDaysBack = self::DEFAULT_DAYS_BACK): string
    {
        try {
            $code = $this->getParamCode($syncType);
            $syncDate = $this->syncDateRepository->findByCode($code);
            
            if ($syncDate && $syncDate->getLastSyncDate()) {
                return $syncDate->getLastSyncDate()->format('Y-m-d H:i:s');
            }
            
            // Si aucune date n'est trouvée, retourner une date par défaut
            return date('Y-m-d H:i:s', strtotime("-$defaultDaysBack days"));
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la récupération de la dernière date de synchronisation', [
                'sync_type' => $syncType,
                'error' => $e->getMessage()
            ]);
            // En cas d'erreur, retourner une date par défaut
            return date('Y-m-d H:i:s', strtotime("-$defaultDaysBack days"));
        }
    }
    
    /**
     * Met à jour la dernière date de synchronisation
     * 
     * @param string $syncType Type de synchronisation (ex: CLIENTS_PRAXEDO)
     * @param string|null $date Date au format Y-m-d H:i:s (utilise la date actuelle si null)
     * @return bool Statut de l'opération
     */
    public function updateLastSyncDate(string $syncType, ?string $date = null): bool
    {
        if ($date === null) {
            $date = date('Y-m-d H:i:s');
        }
        
        try {
            $code = $this->getParamCode($syncType);
            $dateTime = new \DateTime($date);
            
            $syncDate = $this->syncDateRepository->findByCode($code);
            
            if (!$syncDate) {
                // Création d'une nouvelle entrée
                $syncDate = new SyncDate();
                $syncDate->setCode($code);
                $syncDate->setSyncType($syncType);
                $syncDate->setDescription("Date de dernière synchronisation pour $syncType");
            }
            
            $syncDate->setLastSyncDate($dateTime);
            $this->syncDateRepository->save($syncDate);
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour de la dernière date de synchronisation', [
                'sync_type' => $syncType,
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Génère le code du paramètre pour un type de synchronisation
     * 
     * @param string $syncType Type de synchronisation
     * @return string Code du paramètre
     */
    private function getParamCode(string $syncType): string
    {
        return $this::PREFIX . strtoupper($syncType);
    }
} 