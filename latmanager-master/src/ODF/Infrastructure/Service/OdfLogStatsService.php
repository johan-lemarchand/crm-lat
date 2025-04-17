<?php

namespace App\ODF\Infrastructure\Service;

use App\Entity\OdfLog;
use App\ODF\Domain\Repository\OdfExecutionRepositoryInterface;
use App\ODF\Domain\Repository\OdfLogRepositoryInterface;
use App\Service\FormatService;
use Doctrine\DBAL\Connection;

readonly class OdfLogStatsService
{
    public function __construct(
        private OdfLogRepositoryInterface       $odfLogRepository,
        private OdfExecutionRepositoryInterface $odfExecutionRepository,
        private FormatService                   $formatService,
        private Connection                      $connection
    ) {}

    /**
     * Calcule les statistiques globales pour un OdfLog spécifique
     */
    public function calculateOdfLogStats(OdfLog $odfLog): array
    {
        $executions = $this->odfExecutionRepository->findBy(['odfLog' => $odfLog], ['createdAt' => 'DESC']);
        // Regrouper les exécutions par sessionId
        $sessionData = [];
        $totalExecutionTime = 0;
        $totalExecutionTimePause = 0;
        $totalSteps = 0;
        $totalErrors = 0;
        
        foreach ($executions as $execution) {
            $sessionId = $execution->getSessionId() ?? 'unknown';
            
            if (!isset($sessionData[$sessionId])) {
                $sessionData[$sessionId] = [
                    'sessionId' => $sessionId,
                    'executionsCount' => 0,
                    'stepsCount' => 0,
                    'errorsCount' => 0,
                    'lastExecutionAt' => null,
                    'firstCreatedAt' => $execution->getCreatedAt(),
                    'lastUpdatedAt' => $execution->getCreatedAt(),
                    'totalDuration' => 0,
                    'status' => $odfLog->getStatus(),
                    'executionTime' => 0,
                    'executionTimePause' => null,
                    'executions' => [],
                    'userName' => $execution->getUserName()
                ];
            }
            
            $sessionData[$sessionId]['executionsCount']++;
            
            // Compter les étapes et les erreurs
            $stepData = $execution->getStep();
            
            // Vérifier si stepData est déjà un tableau ou une chaîne JSON
            $steps = is_array($stepData) ? $stepData : (is_string($stepData) ? json_decode($stepData, true) : []);
            
            $stepsCount = count($steps);
            $errorsCount = 0;
            
            // Formater les étapes pour l'affichage
            $formattedSteps = [];
            foreach ($steps as $key => $step) {
                if (isset($step['status']) && $step['status'] === 'error') {
                    $errorsCount++;
                }
                
                $formattedSteps[] = [
                    'id' => is_string($key) ? $key : (string)$key,
                    'name' => is_array($step) && isset($step['description']) ? $step['description'] : (is_string($key) ? $key : "Étape $key"),
                    'status' => is_array($step) && isset($step['status']) ? $step['status'] : 'unknown',
                    'time' => is_array($step) && isset($step['execution_time']) ? $step['execution_time'] : null,
                    'details' => is_array($step) ? $step : null
                ];
            }

            // Ajouter cette exécution au groupe
            $executionData = [
                'id' => $execution->getId(),
                'status' => $this->getLastStepStatus($steps),
                'executionTime' => $execution->getDuration(),
                'createdAt' => $execution->getCreatedAt()->format('Y-m-d H:i:s'),
                'step' => json_encode($steps),
                'steps' => $formattedSteps,
                'stepStatus' => $execution->getStepStatus(),
                'stepsStatus' => $execution->getStepStatus(),
                'userName' => $execution->getUserName()
            ];
            
            $sessionData[$sessionId]['executions'][] = $executionData;
            $sessionData[$sessionId]['stepsCount'] += $stepsCount;
            $sessionData[$sessionId]['errorsCount'] += $errorsCount;
            $sessionData[$sessionId]['totalDuration'] += $execution->getDuration() ?? 0;
            $sessionData[$sessionId]['executionTime'] += $execution->getDuration() ?? 0;
            
            $executionDate = $execution->getCreatedAt();
            if (!$sessionData[$sessionId]['lastExecutionAt'] || 
                ($executionDate && $executionDate > $sessionData[$sessionId]['lastExecutionAt'])) {
                $sessionData[$sessionId]['lastExecutionAt'] = $executionDate;
                $sessionData[$sessionId]['lastUpdatedAt'] = $executionDate;
                
                // Mettre à jour le statut de la session avec le stepStatus de la dernière exécution
                if ($execution->getStepStatus()) {
                    $sessionData[$sessionId]['stepsStatus'] = (string)$execution->getStepStatus();
                }
            }
            
            if ($executionDate && $executionDate < $sessionData[$sessionId]['firstCreatedAt']) {
                $sessionData[$sessionId]['firstCreatedAt'] = $executionDate;
            }
            
            $totalSteps += $stepsCount;
            $totalErrors += $errorsCount;
        }
        
        // Convertir le tableau associatif en tableau indexé pour le JSON
        $sessionDataArray = [];
        $counter = 1;
        
        foreach ($sessionData as $sessionId => $session) {
            // Trier les exécutions par ordre décroissant de date de création
            usort($session['executions'], function($a, $b) {
                return strtotime($b['createdAt']) - strtotime($a['createdAt']);
            });
            
            // Trouver l'étape la plus avancée parmi toutes les exécutions
            $highestStepStatus = null;
            foreach ($session['executions'] as $execution) {
                $currentStepStatus = $execution['stepStatus'] ?? null;
                if ($currentStepStatus !== null && ($highestStepStatus === null || $currentStepStatus > $highestStepStatus)) {
                    $highestStepStatus = $currentStepStatus;
                }
            }
            
            // Récupérer le stepStatus de la dernière exécution (la plus récente)
            $lastExecutionStepStatus = !empty($session['executions']) ? ($session['executions'][0]['stepStatus'] ?? null) : null;
            
            $sessionDataArray[] = [
                'id' => $counter++,
                'sessionId' => $session['sessionId'],
                'status' => !empty($session['executions']) ? $session['executions'][0]['status'] : $odfLog->getStatus(),
                'stepsStatus' => $session['stepsStatus'] ?? ($highestStepStatus ? (string)$highestStepStatus : null),
                'executionTime' => $session['executionTime'],
                'executionTimePause' => $session['executionTimePause'],
                'createdAt' => $session['firstCreatedAt']->format('Y-m-d H:i:s'),
                'lastUpdatedAt' => $session['lastUpdatedAt']->format('Y-m-d H:i:s'),
                'executionsCount' => $session['executionsCount'],
                'stepsCount' => $session['stepsCount'],
                'errorsCount' => $session['errorsCount'],
                'totalDuration' => $session['totalDuration'],
                'formattedDuration' => $this->formatService->formatExecutionTime($session['totalDuration']),
                'userName' => $session['userName'],
                'executions' => $session['executions']
            ];
        }
        // Trier les sessions par ordre décroissant de date de création
        usort($sessionDataArray, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });
        
        // Récupérer les temps d'exécution globaux
        $totalExecutionTime = $odfLog->getExecutionTime() ?? 0;
        $totalExecutionTimePause = $odfLog->getExecutionTimePause() ?? 0;
        
        // Calculer la taille des données en base de données pour ce log
        $logSize = $this->calculateLogSize($odfLog->getId());
        
        // Déterminer le statut global à partir de la session la plus récente
        $globalStatus = $odfLog->getStatus();
        if (!empty($sessionDataArray) && isset($sessionDataArray[0]['status'])) {
            $globalStatus = $sessionDataArray[0]['status'];
        }
        
        return [
            'id' => $odfLog->getId(),
            'name' => $odfLog->getName(),
            'status' => $globalStatus,
            'executionTime' => $totalExecutionTime,
            'executionTimePause' => $totalExecutionTimePause,
            'formattedExecutionTime' => $this->formatService->formatExecutionTime($totalExecutionTime),
            'formattedExecutionTimePause' => $this->formatService->formatExecutionTime($totalExecutionTimePause),
            'createdAt' => $odfLog->getCreatedAt()->format('Y-m-d H:i:s'),
            'executionsCount' => count($executions),
            'sessionsCount' => count($sessionData),
            'totalSteps' => $totalSteps,
            'totalErrors' => $totalErrors,
            'errorRate' => $totalSteps > 0 ? round(($totalErrors / $totalSteps) * 100, 2) . '%' : '0%',
            'size' => [
                'bytes' => $logSize,
                'formatted' => $this->formatService->formatBytes($logSize)
            ],
            'sessions' => $sessionDataArray
        ];
    }
    
    /**
     * Récupère le statut final à partir du dernier step d'une exécution
     */
    private function getLastStepStatus(array $steps): string
    {
        if (empty($steps)) {
            return 'unknown';
        }
        
        // Récupérer le dernier step
        $lastStep = end($steps);
        
        // Extraire le statut du dernier step
        if (is_array($lastStep) && isset($lastStep['status'])) {
            return $lastStep['status'];
        }
        
        return 'unknown';
    }
    
    /**
     * Calcule la taille approximative des données en base de données pour un log ODF spécifique
     */
    private function calculateLogSize(int $odfLogId): int
    {
        try {
            // Calculer la taille des données du log principal
            // Comme nous ne pouvons pas obtenir la taille exacte facilement dans SQL Server,
            // nous utilisons une estimation basée sur le nombre de champs et leur type
            $logSize = 1024; // Estimation de base pour un log (1 KB)
            
            // Calculer la taille des exécutions associées
            $executionsSizeQuery = "
                SELECT 
                    COUNT(*) as executions_count
                FROM 
                    odf_execution
                WHERE 
                    odfLog_id = :odf_log_id
            ";
            
            $executionsData = $this->connection->executeQuery($executionsSizeQuery, ['odfLog_id' => $odfLogId])->fetchAssociative() ?: ['executions_count' => 0];
            $executionsCount = (int)($executionsData['executions_count'] ?? 0);
            
            // Récupérer un échantillon des données de step pour estimer leur taille moyenne
            if ($executionsCount > 0) {
                $sampleQuery = "
                    SELECT TOP 5 step
                    FROM odf_execution
                    WHERE odfLog_id = :odf_log_id
                    ORDER BY id DESC
                ";
                
                try {
                    $samples = $this->connection->executeQuery($sampleQuery, ['odfLog_id' => $odfLogId])->fetchAllAssociative();
                    
                    // Calculer la taille moyenne des steps
                    $totalSampleSize = 0;
                    $sampleCount = count($samples);
                    
                    foreach ($samples as $sample) {
                        $stepData = $sample['step'] ?? '[]';
                        $totalSampleSize += strlen($stepData);
                    }
                    
                    $avgStepSize = $sampleCount > 0 ? $totalSampleSize / $sampleCount : 500;
                    $stepsSize = $avgStepSize * $executionsCount;
                } catch (\Exception $e) {
                    // En cas d'erreur, utiliser une estimation
                    $stepsSize = 500 * $executionsCount;
                }
            } else {
                $stepsSize = 0;
            }
            
            // Taille approximative par exécution (structure de base + étapes)
            $executionBaseSize = 512; // Taille approximative de la structure de base d'une exécution
            $totalExecutionsSize = ($executionBaseSize * $executionsCount) + $stepsSize;
            
            // Taille totale (avec un facteur de sécurité pour les autres données)
            return (int)($logSize + $totalExecutionsSize * 1.2);
        } catch (\Exception $e) {
            // En cas d'erreur, retourner une estimation basée sur le nombre d'exécutions
            try {
                $countQuery = "
                    SELECT COUNT(*) as count
                    FROM odf_execution
                    WHERE odfLog_id = :odf_log_id
                ";
                $count = (int)$this->connection->executeQuery($countQuery, ['odfLog_id' => $odfLogId])->fetchOne();
                return 1024 + ($count * 1024); // 1KB par log + 1KB par exécution
            } catch (\Exception $e2) {
                return 2048; // 2 KB par défaut
            }
        }
    }
    
    /**
     * Récupère et calcule les statistiques pour tous les OdfLogs
     */
    public function getAllOdfLogsStats(): array
    {
        $logs = $this->odfLogRepository->findBy([], ['createdAt' => 'DESC'], 10);
        $formattedLogs = [];
        
        foreach ($logs as $log) {
            $formattedLogs[] = $this->calculateOdfLogStats($log);
        }
        
        // Calculer la taille totale de tous les logs
        $totalSize = array_sum(array_map(function($log) {
            return $log['size']['bytes'];
        }, $formattedLogs));
        
        return [
            'logs' => $formattedLogs,
            'totalSize' => [
                'bytes' => $totalSize,
                'formatted' => $this->formatService->formatBytes($totalSize)
            ]
        ];
    }

    /**
     * Récupère et calcule les statistiques pour tous les OdfLogs sans limite
     */
    public function getAllOdfLogsStatsWithoutLimit(): array
    {
        $logs = $this->odfLogRepository->findBy([], ['createdAt' => 'DESC']);
        $formattedLogs = [];
        
        foreach ($logs as $log) {
            $formattedLogs[] = $this->calculateOdfLogStats($log);
        }
        
        // Calculer la taille totale de tous les logs
        $totalSize = array_sum(array_map(function($log) {
            return $log['size']['bytes'];
        }, $formattedLogs));
        
        return [
            'logs' => $formattedLogs,
            'totalSize' => [
                'bytes' => $totalSize,
                'formatted' => $this->formatService->formatBytes($totalSize)
            ]
        ];
    }
} 