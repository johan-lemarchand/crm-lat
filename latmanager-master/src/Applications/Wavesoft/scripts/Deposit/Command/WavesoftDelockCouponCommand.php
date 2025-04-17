<?php

namespace App\Applications\Wavesoft\scripts\Deposit\Command;

use App\Applications\Wavesoft\scripts\Deposit\Service\DelockCouponService;
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
    name: 'Wavesoft:delock_coupon',
    description: 'Déblocage des coupons dans le dépôt Trimble',
)]
class WavesoftDelockCouponCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly DelockCouponService $delockCouponService,
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
        return 'delock_coupon';
    }
    
    /**
     * Affichage des paramètres de la commande
     */
    protected function displayParameters(InputInterface $input): void
    {
        $this->bufferedOutput = new BufferedOutput();
        $this->bufferedIo = new SymfonyStyle($input, $this->bufferedOutput);
        
        $executionId = $this->execution->getId();
        $this->delockCouponService->setExecution($this->execution);
        
        $message = 'Déblocage des coupons dans le dépôt Trimble';
        $this->bufferedOutput->writeln($message);
        $this->io->title($message);
    }

    /**
     * Exécute la logique métier spécifique de déblocage des coupons
     */
    protected function executeSyncLogic(InputInterface $input, OutputInterface $output): array
    {
        $result = $this->delockCouponService->DelockCoupon();
        
        // Préparation des statistiques
        $stats = [
            'total' => $result['total'],
            'success_count' => count(array_filter($result['processed'], fn($item) => $item['status'] === 'success')),
            'error_count' => count(array_filter($result['processed'], fn($item) => $item['status'] === 'error')),
            'processed' => $result['processed']
        ];
        
        // Afficher les résultats
        $this->displayResults($stats);
        
        return $stats;
    }
    
    /**
     * Affiche les résultats du déblocage
     */
    private function displayResults(array $stats): void
    {
        $this->io->section('Résumé du déblocage des coupons');
        
        if ($stats['total'] === 0) {
            $this->io->info('Aucun coupon à traiter aujourd\'hui.');
            return;
        }
        
        $tableData = [
            ['Coupons traités', $stats['total']],
            ['Succès', $stats['success_count']],
            ['Erreurs', $stats['error_count']],
        ];
        
        $this->io->table(['Métrique', 'Valeur'], $tableData);
        
        if ($stats['total'] > 0) {
            $this->io->section('Détails des coupons traités');
            
            $headers = ['Numéro de coupon', 'Statut', 'Message'];
            $rows = [];
            
            foreach ($stats['processed'] as $coupon) {
                $rows[] = [
                    $coupon['pcdnum'],
                    $coupon['status'],
                    $coupon['message'] ?? '-'
                ];
            }
            
            $this->io->table($headers, $rows);
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
            'total_coupons' => $stats['total'],
            'success_count' => $stats['success_count'],
            'error_count' => $stats['error_count'],
            'details' => $stats['processed']
        ];

        // Envoi de l'email
        try {
            if ($stats['total'] > 0) {
                $this->emailService->sendDelockCouponReport($resume, $this->dbCommand);
            }
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
        try {
            $resume = [
                'total_coupons' => 0,
                'success_count' => 0,
                'error_count' => 1,
                'details' => [
                    [
                        'pcdnum' => 'N/A',
                        'status' => 'error',
                        'message' => sprintf(
                            'Une erreur est survenue lors du déblocage des coupons : %s',
                            $e->getMessage()
                        )
                    ]
                ]
            ];
            
            $this->emailService->sendDelockCouponReport($resume, $this->dbCommand);
        } catch (\Exception $emailException) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email d\'erreur', [
                'error' => $emailException->getMessage(),
                'trace' => $emailException->getTraceAsString()
            ]);
        }
    }
} 