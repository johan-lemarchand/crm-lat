<?php

namespace App\Utils;

class EventManager
{
    private array $events = [];

    public function sendEvent(string $type, array $data): void
    {
        $this->events[] = [
            'type' => $type,
            'data' => $data,
            'timestamp' => microtime(true)
        ];
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}
