<?php

namespace App\Shared\Utils;

use App\Applications\Wavesoft\WavesoftClient;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gère le mode test pour les différents services et commandes
 */
class TestModeManager
{
    private bool $isTestMode = false;
    
    /**
     * Configure le mode test en fonction de l'environnement
     * 
     * @param string|null $appEnv Environnement de l'application (null pour utiliser $_ENV['APP_ENV'])
     * @return bool Retourne true si le mode test est activé, false sinon
     */
    public function configureTestMode(?string $appEnv = null): bool
    {
        $env = $appEnv ?? ($_ENV['APP_ENV'] ?? 'prod');
        $this->isTestMode = ($env === 'dev' || $env === 'test');
        
        return $this->isTestMode;
    }
    
    /**
     * Vérifie si le mode test est activé
     * 
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->isTestMode;
    }
    
    /**
     * Active ou désactive le mode test
     * 
     * @param bool $testMode
     * @return void
     */
    public function setTestMode(bool $testMode): void
    {
        $this->isTestMode = $testMode;
    }
    
    /**
     * Configure un client Wavesoft pour le mode test si nécessaire
     * 
     * @param WavesoftClient $wavesoftClient
     * @return void
     */
    public function configureWavesoftClient(WavesoftClient $wavesoftClient): void
    {
        if ($this->isTestMode) {
            $wavesoftClient->useTestDatabase();
        }
    }
    
    /**
     * Affiche des messages de mode test dans les IO de Symfony
     * 
     * @param SymfonyStyle $io Interface de sortie principale
     * @param SymfonyStyle|null $bufferedIo Interface de sortie bufferisée (optionnelle)
     * @return void
     */
    public function notifyTestMode(SymfonyStyle $io, ?SymfonyStyle $bufferedIo = null): void
    {
        if ($this->isTestMode) {
            $io->note('Exécution en mode test - Base de données de test');

            $bufferedIo?->note('Exécution en mode test - Base de données de test');
        }
    }
} 