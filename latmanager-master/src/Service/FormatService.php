<?php

namespace App\Service;

class FormatService
{
    /**
     * Formate une taille en octets en une chaîne lisible (B, KB, MB, GB)
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = min(floor(log($bytes) / log(1024)), count($units) - 1);
        $value = round($bytes / pow(1024, $base), 2);
        return $value . ' ' . $units[$base];
    }
    
    /**
     * Formate le temps d'exécution en minutes et secondes
     */
    public function formatExecutionTime(float $milliseconds): string
    {
        $seconds = $milliseconds / 1000;
        
        if ($seconds < 60) {
            return sprintf('%.2f sec', $seconds);
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return sprintf('%d min %02.2f sec', $minutes, $remainingSeconds);
    }
} 