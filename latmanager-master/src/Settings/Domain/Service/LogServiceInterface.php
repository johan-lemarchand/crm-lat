<?php

namespace App\Settings\Domain\Service;

interface LogServiceInterface
{
    public function getPhpLogs(): array;
    public function getApacheLogs(): array;
    public function getDbStats(): array;
    public function clearLogs(string $type, ?string $commandId = null, ?string $logType = null): void;
    public function formatSize(int $bytes): string;
} 