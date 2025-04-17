<?php

namespace App\Applications\Wavesoft\scripts\Currency\Command;

use App\Applications\Wavesoft\scripts\Currency\Service\CurrencyService;
use App\Command\AbstractSyncCommand;
use App\Entity\LogResume;
use App\Service\CommandLogger;
use App\Service\EmailService;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'Wavesoft:currency',
    description: 'Synchronisation des taux de change des devises',
)]
class WavesoftCurrenciesCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly CurrencyService $currencyService,
        LoggerInterface $logger,
        CommandLogger $commandLogger,
        ManagerRegistry $doctrine,
        private readonly EmailService $emailService
    ) {
        parent::__construct($logger, $commandLogger, $doctrine);
    }

    /**
     * Retourne le nom de la commande pour la recherche en base
     */
    protected function getCommandName(): string
    {
        return 'Wavesoft';
    }

    /**
     * Retourne le nom du script pour la recherche en base
     */
    protected function getScriptName(): string
    {
        return 'currency';
    }
    
    /**
     * Affichage des paramètres de la commande
     */
    protected function displayParameters(InputInterface $input): void
    {
        $this->bufferedOutput = new BufferedOutput();
        $this->bufferedIo = new SymfonyStyle($input, $this->bufferedOutput);
        
        $executionId = $this->execution->getId();
        $this->currencyService->setExecution($this->execution);
        
        $message = 'Synchronisation des taux de change des devises';
        $this->bufferedOutput->writeln($message);
        $this->io->title($message);
    }

    /**
     * Exécute la logique métier spécifique de synchronisation des devises
     */
    protected function executeSyncLogic(InputInterface $input, OutputInterface $output): array
    {
        $stats = $this->currencyService->synchronizeCurrencies();
        
        // Afficher les résultats
        $this->displayResults($stats);
        
        return $stats;
    }
    
    /**
     * Affiche les résultats de la synchronisation
     */
    private function displayResults(array $stats): void
    {
        $this->io->section('Résumé de la synchronisation');
        
        $tableData = [
            ['Devises analysées', $stats['total']],
            ['Devises mises à jour', $stats['updated']],
            ['Erreurs', $stats['errors']],
        ];
        
        $this->io->table(['Métrique', 'Valeur'], $tableData);
        
        if ($stats['updated'] > 0) {
            $this->io->section('Détails des mises à jour');
            
            $headers = ['Devise', 'Ancien taux', 'Nouveau taux', 'Taux BCE', 'Dernière mise à jour'];
            $rows = [];
            
            foreach ($stats['currencies'] as $currency) {
                $summary = $currency['summary'] ?? [
                    'last_update' => $currency['last_update'] ?? 'N/A',
                    'old_rate' => $currency['old_rate'] ?? 'N/A',
                    'new_rate' => $currency['new_rate'] ?? 'N/A',
                    'bce_rate' => $currency['bce_rate'] ?? 'N/A'
                ];
                
                $rows[] = [
                    $currency['code'] ?? 'N/A',
                    $summary['old_rate'] ?? $currency['old_rate'] ?? 'N/A',
                    $summary['new_rate'] ?? $currency['new_rate'] ?? 'N/A',
                    $summary['bce_rate'] ?? $currency['bce_rate'] ?? 'N/A',
                    $summary['last_update'] ?? $currency['last_update'] ?? 'N/A'
                ];
            }
            
            $this->io->table($headers, $rows);
        }
        
        if ($stats['errors'] > 0 && !empty($stats['error_details'])) {
            $this->io->section('Erreurs rencontrées');
            
            foreach ($stats['error_details'] as $error) {
                $this->io->error($error);
            }
        }
    }
    
    /**
     * Crée et enregistre le résumé d'exécution avec envoi d'email
     */
    protected function createAndSaveResume(array $stats, float $duration): void
    {
        // Création du résumé standard via la classe parent
        parent::createAndSaveResume($stats, $duration);
        
        // Préparation des données pour l'email
        $resume = [
            'date' => date('Y-m-d H:i:s'),
            'statistiques' => [
                'total_devises' => $stats['total'],
                'mises_a_jour' => $stats['updated'],
                'erreurs' => $stats['errors'],
                'temps_execution' => round($duration, 2).'s',
                'status_command' => ($stats['errors'] === 0) ? 'success' : 'error',
                'currencies' => array_map(function($currencyData) {
                    return $currencyData['summary'] ?? [
                        'last_update' => $currencyData['last_update'] ?? 'N/A',
                        'old_rate' => $currencyData['old_rate'] ?? 'N/A',
                        'new_rate' => $currencyData['new_rate'] ?? 'N/A',
                        'bce_rate' => $currencyData['bce_rate'] ?? 'N/A'
                    ];
                }, $stats['currencies']),
                'resultats' => [
                    'status' => ($stats['errors'] === 0) ? 'success' : 'error',
                    'message' => sprintf(
                        'Synchronisation terminée en %.2fs. %d devises mises à jour, %d erreurs.',
                        $duration,
                        $stats['updated'],
                        $stats['errors']
                    ),
                    'details' => $stats['error_details'] ?? []
                ],
            ],
        ];

        // Envoi de l'email
        try {
            $this->emailService->sendCurrencySyncReport($resume, $this->dbCommand);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de rapport', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Gestion des exceptions
     */
    protected function handleException(\Exception $e): void
    {
        parent::handleException($e);
        
        // Envoi d'un email d'erreur
        $resume = [
            'date' => date('Y-m-d H:i:s'),
            'statistiques' => [
                'total_devises' => 0,
                'mises_a_jour' => 0,
                'erreurs' => 1,
                'temps_execution' => isset($this->startTime) ? round(microtime(true) - $this->startTime, 2).'s' : 'N/A',
                'status_command' => 'error',
                'resultats' => [
                    'status' => 'error',
                    'message' => sprintf(
                        'Une erreur est survenue lors de la synchronisation des devises : %s',
                        $e->getMessage()
                    ),
                    'details' => [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ],
                ],
            ],
        ];
        
        try {
            $this->emailService->sendCurrencySyncReport($resume, $this->dbCommand);
        } catch (\Exception $emailException) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email d\'erreur', [
                'error' => $emailException->getMessage(),
                'trace' => $emailException->getTraceAsString()
            ]);
        }
    }
} 