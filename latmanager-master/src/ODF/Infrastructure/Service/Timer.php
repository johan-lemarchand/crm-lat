<?php

namespace App\ODF\Infrastructure\Service;

use Psr\Log\LoggerInterface;

class Timer
{
    private array $steps = [];
    private float $startTime;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        $this->startTime = microtime(true);
    }

    public function logStep(string $step, string $level = 'info', string $status = 'info', string $message = ''): void
    {
        $currentTime = microtime(true);
        $elapsedTime = $currentTime - $this->startTime;

        $stepInfo = [
            'step' => $step,
            'time' => date('Y-m-d H:i:s'),
            'elapsed' => round($elapsedTime, 3),
            'level' => $level,
            'status' => $status,
            'message' => $message
        ];

        $this->steps[] = $stepInfo;

        // Log the step
        $logMessage = sprintf(
            '[%s] %s (%.3fs) - Status: %s - %s',
            $stepInfo['time'],
            $step,
            $stepInfo['elapsed'],
            $status,
            $message
        );

        switch ($level) {
            case 'error':
                $this->logger->error($logMessage);
                break;
            case 'warning':
                $this->logger->warning($logMessage);
                break;
            default:
                $this->logger->info($logMessage);
        }
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function reset(): void
    {
        $this->steps = [];
        $this->startTime = microtime(true);
    }
}
