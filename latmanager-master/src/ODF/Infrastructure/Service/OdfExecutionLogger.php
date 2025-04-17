<?php

namespace App\ODF\Infrastructure\Service;

use App\Entity\OdfExecution;
use App\Entity\OdfLog;
use App\ODF\Domain\Repository\OdfExecutionRepositoryInterface;
use App\ODF\Domain\Repository\OdfLogRepositoryInterface;

class OdfExecutionLogger
{
    private array $steps = [];
    private float $startTime;
    private string $controller;
    private ?OdfExecution $currentExecution = null;
    private ?OdfLog $currentOdfLog = null;

    public function __construct(
        private readonly OdfExecutionRepositoryInterface $odfExecutionRepository,
        private readonly OdfLogRepositoryInterface $odfLogRepository
    ) {
        $this->startTime = microtime(true);
    }

    public function startLogging(string $controller, string $description = null, ?int $pcdid = null, ?string $pcdnum = null, ?string $sessionId = null, ?int $stepStatus = 1): void
    {
        $this->controller = $controller;
        $this->currentExecution = new OdfExecution();
        
        if ($pcdnum) {
            $this->currentOdfLog = $this->odfLogRepository->findOrCreate($pcdnum);
            if ($this->currentOdfLog) {
                if ($sessionId) {
                    $this->currentOdfLog->setSessionId($sessionId);
                    $this->currentExecution->setSessionId($sessionId);
                } else if ($this->currentOdfLog->getSessionId()) {
                    $this->currentExecution->setSessionId($this->currentOdfLog->getSessionId());
                }
                
                $this->currentOdfLog->addOdfExecution($this->currentExecution);
                
                $attemptNumber = $this->currentExecution->getAttemptNumber();
                $description = sprintf("%s (Tentative #%d)", $description ?? 'Entrée dans le controller', $attemptNumber);
            }
        }
        
        if ($stepStatus !== null) {
            $this->currentExecution->setStepStatus($stepStatus);
        }
        
        $this->addStep(
            description: $description ?? 'Entrée dans le controller',
            status: 'success',
            message: 'Début de l\'exécution' . ($sessionId ? ' (Session: ' . $sessionId . ')' : ''),
            stepStatus: $stepStatus
        );
    }

    public function startHandler(string $handlerName, array $params = [], ?int $stepStatus = null): float
    {
        $startTime = microtime(true);
        
        if (!$this->currentOdfLog && isset($params['pcdnum'])) {
            $this->currentOdfLog = $this->odfLogRepository->findOneBy(['name' => $params['pcdnum']]);
            if ($this->currentOdfLog && $this->currentExecution) {
                $this->currentExecution->setOdfLog($this->currentOdfLog);
                
                if (isset($params['sessionId']) && $params['sessionId']) {
                    $this->currentOdfLog->setSessionId($params['sessionId']);
                    $this->currentExecution->setSessionId($params['sessionId']);
                } else if ($this->currentOdfLog->getSessionId()) {
                    $this->currentExecution->setSessionId($this->currentOdfLog->getSessionId());
                }
            }
        }
        
        $this->addStep(
            description: "Entrée dans $handlerName",
            status: 'info',
            message: 'Début du traitement ' . ($params ? ' avec paramètres: ' . json_encode($params) : ''),
            stepStartTime: $startTime,
            stepStatus: $stepStatus
        );
        return $startTime;
    }

    public function finishHandler(string $handlerName, float $startTime, array $result = [], ?int $stepStatus = null): void
    {
        // Déterminer le statut en fonction du résultat
        $status = 'success';
        if (isset($result['status']) && $result['status'] === 'error') {
            $status = 'error';
        }
        
        $this->addStep(
            description: "Sortie de $handlerName",
            status: $status,
            message: 'Traitement terminé' . ($result ? ' avec résultat: ' . json_encode($result) : ''),
            stepStartTime: $startTime,
            stepStatus: $stepStatus
        );
    }

    public function logApiCall(
        string $apiName,
        string $endpoint,
        array $request,
        ?array $response = null,
        ?string $error = null,
        ?int $stepStatus = null
    ): void {
        $this->addStep(
            description: "Appel API $apiName",
            status: $error ? 'error' : 'info',
            message: json_encode([
                'endpoint' => $endpoint,
                'request' => $request,
                'response' => $response,
                'error' => $error
            ]),
            stepStatus: $stepStatus
        );
    }

    public function addStep(
        string $description,
        string $status,
        string|array $message,
        ?float $stepStartTime = null,
        ?int $stepStatus = null,
        ?string $user = null
    ): void {
        $executionTimeMs = round(((microtime(true) - ($stepStartTime ?? $this->startTime))) * 1000);
        $formattedTime = $this->formatExecutionTime($executionTimeMs);
        
        $this->steps[] = [
            'controller' => $this->controller,
            'description' => $description,
            'status' => $status,
            'message' => is_array($message) ? $message : ['text' => $message],
            'execution_time' => $executionTimeMs,
            'formatted_time' => $formattedTime,
            'step_status' => $stepStatus
        ];

        if ($this->currentExecution) {
            $this->currentExecution->setStep($this->steps);
            if ($user) {
                $this->currentExecution->setUserName($user);
            }
            $this->currentExecution->setDuration(round((microtime(true) - $this->startTime) * 1000));
            
            if ($stepStatus !== null) {
                $this->currentExecution->setStepStatus($stepStatus);
            }
            
            // Si on a un OdfLog parent, on le sauvegarde directement
            if ($this->currentOdfLog) {
                $this->odfLogRepository->save($this->currentOdfLog, true);
            }
        }
    }

    public function logError(string $description, string $message, ?string $user= null, ?\Throwable $error = null, ?int $stepStatus = null): void
    {
        $totalTimeMs = round((microtime(true) - $this->startTime) * 1000);
        $formattedTotalTime = $this->formatExecutionTime($totalTimeMs);
        
        $errorMessage = $message . ($error ? ': ' . $error->getMessage() : '') . ' (après ' . $formattedTotalTime . ')';
        
        $this->addStep(
            description: $description,
            status: 'error',
            message: $errorMessage,
            stepStatus: $stepStatus,
            user: $user
        );
        
        // Sauvegarder explicitement pour s'assurer que l'erreur est enregistrée
        if ($this->currentExecution && $this->currentOdfLog) {
            if ($user && is_string($user)) {
                // S'assurer que l'utilisateur ne dépasse pas 250 caractères
                $this->currentExecution->setUserName(substr($user, 0, 250));
            } else {
                // S'assurer qu'un utilisateur par défaut est défini
                $this->currentExecution->setUserName('unknown');
            }
            $this->odfLogRepository->save($this->currentOdfLog, true);
        }
    }

    /**
     * Enregistre une étape de type "retry" sans terminer l'exécution
     */
    public function logRetry(string $description, string $message, int $currentTry, int $maxRetries, ?int $stepStatus = null): void
    {
        $totalTimeMs = round((microtime(true) - $this->startTime) * 1000);
        $formattedTotalTime = $this->formatExecutionTime($totalTimeMs);
        
        $this->addStep(
            description: $description,
            status: 'info',
            message: $message . ' - Tentative ' . $currentTry . '/' . $maxRetries . ' (après ' . $formattedTotalTime . ')',
            stepStatus: $stepStatus
        );
        
        // Ne pas appeler save() pour ne pas terminer l'exécution
        // Mais sauvegarder quand même l'état actuel
        if ($this->currentExecution && $this->currentOdfLog) {
            $this->odfLogRepository->save($this->currentOdfLog, true);
        }
    }

    public function finish(
        string $description = 'Sortie du controller',
        string $message = 'Exécution terminée',
        ?int $stepStatus = null,
        ?string $user = 'unknown',
        ?string $status = 'success'
    ): void
    {
        $this->addStep(
            description: $description,
            status: $status,
            message: $message,
            stepStatus: $stepStatus
        );

        if ($this->currentExecution) {
            $this->currentExecution->setUserName($user);
            $this->odfExecutionRepository->save($this->currentExecution, true);
        }
    }

    private function formatExecutionTime(float $milliseconds): string
    {
        $seconds = floor($milliseconds / 1000);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);

        $milliseconds = $milliseconds % 1000;
        $seconds = $seconds % 60;
        $minutes = $minutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        if ($minutes > 0 || $hours > 0) {
            $parts[] = $minutes . 'm';
        }
        if ($seconds > 0 || $minutes > 0 || $hours > 0) {
            $parts[] = $seconds . 's';
        }
        if ($milliseconds > 0) {
            $parts[] = $milliseconds . 'ms';
        }

        return implode(' ', $parts);
    }
} 