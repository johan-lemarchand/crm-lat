<?php

namespace App\Applications\Praxedo\scripts\Interventions\Command;

use AllowDynamicProperties;
use App\Applications\Praxedo\scripts\Interventions\Service\InterventionCleanupService;
use App\Command\AbstractSyncCommand;
use App\Service\CommandLogger;
use App\Service\EmailService;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AllowDynamicProperties] #[AsCommand(
    name: 'Praxedo:intervention-cleanup',
    description: 'Nettoyage des interventions dans Praxedo',
)]
class InterventionCleanupCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly InterventionCleanupService $cleanupService,
        LoggerInterface          $logger,
        CommandLogger            $commandLogger,
        ManagerRegistry          $doctrine,
        private readonly EmailService               $emailService
    ) {
        parent::__construct($logger, $commandLogger, $doctrine);
    }

    protected function getCommandName(): string
    {
        return 'Praxedo';
    }

    protected function getScriptName(): string
    {
        return 'intervention-cleanup';
    }
    
    protected function displayParameters(InputInterface $input): void
    {
        $this->bufferedOutput = new BufferedOutput();
        $this->bufferedIo = new SymfonyStyle($input, $this->bufferedOutput);
        
        $this->cleanupService->setExecution($this->execution);
        
        $message = 'Nettoyage des interventions dans Praxedo';
        $this->bufferedOutput->writeln($message);
        $this->io->title($message);
    }

    /**
     * @throws Exception
     * @throws ORMException
     */
    protected function executeSyncLogic(InputInterface $input, OutputInterface $output): array
    {
        $result = $this->cleanupService->cleanupInterventions();
        
        $this->displayResults($result);
        
        return $result;
    }
    
    private function displayResults(array $stats): void
    {
        $this->io->section('Résumé du nettoyage des interventions');
        
        if ($stats['total'] === 0) {
            $this->io->info('Aucune intervention à traiter aujourd\'hui.');
            return;
        }
        
        $tableData = [
            ['Interventions traitées', $stats['total']],
            ['Annulées', $stats['cancelled']],
            ['Mises à jour', $stats['updated']],
            ['Erreurs', $stats['errors']],
        ];
        
        $this->io->table(['Métrique', 'Valeur'], $tableData);
        
        if ($stats['total'] > 0) {
            $this->io->section('Détails des interventions traitées');
            
            $headers = ['ID', 'Statut', 'Message'];
            $rows = [];
            
            foreach ($stats['details'] as $detail) {
                $rows[] = [
                    $detail['id'],
                    $detail['status'],
                    $detail['message']
                ];
            }
            
            $this->io->table($headers, $rows);
        }
    }
    
    protected function createAndSaveResume(array $stats, float $duration): void
    {
        // Création du résumé standard via la classe parent
        parent::createAndSaveResume($stats, $duration);
        
        // Préparation des données pour l'email
        $resume = [
            'total_interventions' => $stats['total'],
            'cancelled_count' => $stats['cancelled'],
            'updated_count' => $stats['updated'],
            'error_count' => $stats['errors'],
            'details' => $stats['details']
        ];

        // Envoi de l'email
        try {
            if ($stats['total'] > 0) {
                $this->emailService->sendInterventionCleanupReport($resume, $this->dbCommand);
            }
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de rapport', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    protected function handleException(Exception $e): void
    {
        parent::handleException($e);
        
        // Envoi d'un email d'erreur
        try {
            $resume = [
                'total_interventions' => 0,
                'cancelled_count' => 0,
                'updated_count' => 0,
                'error_count' => 1,
                'details' => [
                    [
                        'id' => 'N/A',
                        'status' => 'error',
                        'message' => sprintf(
                            'Une erreur est survenue lors du nettoyage des interventions : %s',
                            $e->getMessage()
                        )
                    ]
                ]
            ];
            
            $this->emailService->sendInterventionCleanupReport($resume, $this->dbCommand);
        } catch (Exception $emailException) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email d\'erreur', [
                'error' => $emailException->getMessage(),
                'trace' => $emailException->getTraceAsString()
            ]);
        }
    }
} 