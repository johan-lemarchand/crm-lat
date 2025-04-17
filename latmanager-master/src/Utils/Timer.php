<?php

namespace App\Utils;

class Timer
{
    private float $startTime;
    private array $steps = [];

    public function start(): void
    {
        $this->startTime = microtime(true);
    }

    public function logStep(string $title, string $type, string $status, string $message): void
    {
        $this->steps[] = [
            'title' => $title,
            'type' => $type,
            'status' => $status,
            'message' => $message,
            'time' => microtime(true) - $this->startTime
        ];
    }

    public function getSteps(): array
    {
        return $this->steps;
    }
}
