<?php

namespace App\Service;

use App\Entity\ExecutionLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour la gestion des logs d'exécution des commandes
 */
class ExecutionLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }
    
    /**
     * Crée un nouveau log d'exécution
     * 
     * @param string $executionId L'identifiant unique de l'exécution
     * @param string $commandName Le nom de la commande
     * @param string $startDate La date de début d'exécution
     * @param string $status Le statut de l'exécution
     * @param string $message Un message détaillant l'exécution
     * @return ExecutionLog
     */
    public function createLog(string $executionId, string $commandName, string $startDate, string $status, string $message): ExecutionLog
    {
        try {
            $log = new ExecutionLog();
            $log->setExecutionId($executionId);
            $log->setCommandName($commandName);
            $log->setStartDate(new \DateTime($startDate));
            $log->setStatus($status);
            $log->setMessage($message);
            
            $this->entityManager->persist($log);
            $this->entityManager->flush();
            
            return $log;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création du log d\'exécution', [
                'execution_id' => $executionId,
                'command_name' => $commandName,
                'error' => $e->getMessage()
            ]);
            
            // On crée quand même un objet log, mais sans le persister
            $log = new ExecutionLog();
            $log->setExecutionId($executionId);
            $log->setCommandName($commandName);
            $log->setStartDate(new \DateTime($startDate));
            $log->setStatus('ERROR');
            $log->setMessage('Erreur lors de la création du log : ' . $e->getMessage());
            
            return $log;
        }
    }
    
    /**
     * Met à jour un log d'exécution existant
     * 
     * @param string $executionId L'identifiant unique de l'exécution
     * @param string $status Le nouveau statut de l'exécution
     * @param string $message Le nouveau message de l'exécution
     * @param string|null $endDate La date de fin d'exécution (si null, la date actuelle est utilisée)
     * @return bool Succès de l'opération
     */
    public function updateLog(string $executionId, string $status, string $message, ?string $endDate = null): bool
    {
        try {
            $log = $this->entityManager->getRepository(ExecutionLog::class)->findOneBy([
                'executionId' => $executionId
            ]);
            
            if (!$log) {
                $this->logger->warning('Tentative de mise à jour d\'un log inexistant', [
                    'execution_id' => $executionId
                ]);
                return false;
            }
            
            $log->setStatus($status);
            $log->setMessage($message);
            $log->setEndDate(new \DateTime($endDate ?? 'now'));
            
            // Calculer la durée en secondes
            $startDate = $log->getStartDate();
            $endDateTime = new \DateTime($endDate ?? 'now');
            $duration = $endDateTime->getTimestamp() - $startDate->getTimestamp();
            $log->setDuration($duration);
            
            $this->entityManager->flush();
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour du log d\'exécution', [
                'execution_id' => $executionId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Récupère un log d'exécution par son ID
     * 
     * @param string $executionId L'identifiant de l'exécution
     * @return ExecutionLog|null Le log d'exécution ou null si non trouvé
     */
    public function getLog(string $executionId): ?ExecutionLog
    {
        try {
            return $this->entityManager->getRepository(ExecutionLog::class)->findOneBy([
                'executionId' => $executionId
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du log d\'exécution', [
                'execution_id' => $executionId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Récupère les logs d'exécution pour une commande donnée
     * 
     * @param string $commandName Le nom de la commande
     * @param int $limit Nombre maximum de logs à récupérer
     * @return array Les logs d'exécution
     */
    public function getLogsByCommand(string $commandName, int $limit = 10): array
    {
        try {
            return $this->entityManager->getRepository(ExecutionLog::class)->findBy(
                ['commandName' => $commandName],
                ['startDate' => 'DESC'],
                $limit
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération des logs d\'exécution par commande', [
                'command_name' => $commandName,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
} 