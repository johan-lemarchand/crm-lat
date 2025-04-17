<?php

namespace App\Command;

use App\Entity\Command;
use App\Entity\CommandExecution;
use App\Entity\LogResume;
use App\Service\CommandLogger;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Classe de base pour toutes les commandes de synchronisation
 * Gère la logique commune de gestion d'exécution, logs, résumés, etc.
 */
abstract class AbstractSyncCommand extends SymfonyCommand
{
    protected CommandExecution $execution;
    protected Command $dbCommand;
    protected float $startTime;
    protected SymfonyStyle $io;
    protected BufferedOutput $bufferedOutput;
    
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly CommandLogger $commandLogger,
        protected readonly ManagerRegistry $doctrine,
    ) {
        parent::__construct();
    }

    /**
     * À implémenter dans les classes enfants pour définir le nom de la commande
     */
    abstract protected function getCommandName(): string;
    
    /**
     * À implémenter dans les classes enfants pour définir le nom du script
     */
    abstract protected function getScriptName(): string;
    
    /**
     * À implémenter dans les classes enfants pour exécuter la logique métier de la commande
     */
    abstract protected function executeSyncLogic(InputInterface $input, OutputInterface $output): array;
    
    /**
     * Peut être surchargé pour personnaliser l'affichage des paramètres
     */
    protected function displayParameters(InputInterface $input): void
    {
        // À surcharger si besoin
    }
    
    /**
     * Configure les options communes à toutes les commandes de synchronisation
     */
    protected function configure(): void
    {
        $this->addOption('execution-id', null, InputOption::VALUE_REQUIRED, 'ID de l\'exécution');
    }
    
    /**
     * Exécute la commande
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bufferedOutput = new BufferedOutput();
        $this->io = new SymfonyStyle($input, $output);
        
        try {
            // Initialise l'exécution
            $this->initializeExecution();
            
            // Affiche les paramètres
            $this->displayParameters($input);
            
            // Démarrage du timer
            $this->startTime = microtime(true);
            
            // Exécute la logique métier spécifique
            $stats = $this->executeSyncLogic($input, $output);
            
            // Calcul de la durée
            $duration = microtime(true) - $this->startTime;
            
            // Création et enregistrement du résumé
            $this->createAndSaveResume($stats, $duration);
            
            return SymfonyCommand::SUCCESS;
        } catch (\Exception $e) {
            $this->handleException($e);
            return SymfonyCommand::FAILURE;
        }
    }
    
    /**
     * Initialise l'exécution de la commande
     */
    protected function initializeExecution(): void
    {
        // Recherche de la commande dans la base
        $this->dbCommand = $this->doctrine->getRepository(Command::class)
            ->findOneBy([
                'name' => $this->getCommandName(),
                'scriptName' => $this->getScriptName(),
            ]);

        // Création de l'exécution
        $this->execution = new CommandExecution();
        $this->execution->setCommand($this->dbCommand);
        $this->execution->setStartedAt(new \DateTime());
        $this->execution->setStatus('running');

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($this->execution);
        $entityManager->flush();

        $this->dbCommand->setLastStatus('running');
        $entityManager->flush();
        
        $executionId = $this->execution->getId();
        if ($executionId === null) {
            throw new \RuntimeException('L\'ID d\'exécution n\'a pas pu être généré.');
        }

        $this->commandLogger->setCurrentExecution($this->execution);
    }
    
    /**
     * Crée et enregistre le résumé d'exécution
     */
    protected function createAndSaveResume(array $stats, float $duration): void
    {
        // Déterminer le statut global
        $hasErrors = false;
        $errorDetails = [];
        
        // Parcourir les stats pour chercher des erreurs
        foreach ($stats as $section => $sectionStats) {
            if (is_array($sectionStats) && isset($sectionStats['errors']) && $sectionStats['errors'] > 0) {
                $hasErrors = true;
            }
            
            if (is_array($sectionStats) && isset($sectionStats['error_details']) && !empty($sectionStats['error_details'])) {
                $errorDetails[$section] = $sectionStats['error_details'];
            }
        }
        
        // Calcul du total d'analyses
        $totalAnalyses = 0;
        foreach ($stats as $section => $sectionStats) {
            if (is_array($sectionStats) && isset($sectionStats['total'])) {
                $totalAnalyses += $sectionStats['total'];
            }
        }
        
        // Création du message de résultat
        $resultMessage = sprintf(
            'Synchronisation terminée en %.2fs. ',
            $duration
        );
        
        // Compléter avec les détails des sections
        foreach ($stats as $section => $sectionStats) {
            if (is_array($sectionStats)) {
                $resultMessage .= $this->generateSectionSummary($section, $sectionStats);
            }
        }
        
        // Création du résumé
        $resume = [
            'date' => date('Y-m-d H:i:s'),
            'statistiques' => [
                'total_analyses' => $totalAnalyses,
                'temps_execution' => round($duration, 2).'s',
                'status_command' => $hasErrors ? 'error' : 'success',
                'resultats' => [
                    'status' => $hasErrors ? 'error' : 'success',
                    'message' => $resultMessage
                ]
            ]
        ];
        
        // Ajouter les statistiques détaillées
        foreach ($stats as $section => $sectionStats) {
            if (is_array($sectionStats)) {
                $resume['statistiques'][$section] = $sectionStats;
            }
        }

        // Enregistrer le résumé
        $logResume = new LogResume();
        $logResume->setCommand($this->dbCommand);
        $logResume->setExecutionDate($this->execution->getStartedAt());
        $logResume->setResume(json_encode($resume));
        $this->doctrine->getManager()->persist($logResume);

        // Mise à jour du statut de la commande
        $this->dbCommand->setLastStatus($resume['statistiques']['status_command']);
        $this->doctrine->getManager()->flush();

        // Finaliser la commande
        $this->commandLogger->finishCommand(
            $this->execution, 
            '', 
            $resume['statistiques']['status_command']
        );

        // Afficher le résumé
        $this->io->success($resume['statistiques']['resultats']['message']);
    }
    
    /**
     * Génère un résumé textuel pour une section de statistiques
     */
    protected function generateSectionSummary(string $section, array $stats): string
    {
        if (!isset($stats['total'])) {
            return '';
        }
        
        $summary = sprintf('%d %s synchronisés (', $stats['total'], $section);
        
        $details = [];
        
        if (isset($stats['created'])) {
            $details[] = sprintf('%d créés', $stats['created']);
        }
        
        if (isset($stats['created_customers'])) {
            $details[] = sprintf('%d créés', $stats['created_customers']);
        }
        
        if (isset($stats['updated'])) {
            $details[] = sprintf('%d mis à jour', $stats['updated']);
        }
        
        if (isset($stats['deleted'])) {
            $details[] = sprintf('%d supprimés', $stats['deleted']);
        }
        
        if (isset($stats['created_locations'])) {
            $details[] = sprintf('%d emplacements créés', $stats['created_locations']);
        }
        
        if (isset($stats['errors'])) {
            $details[] = sprintf('%d erreurs', $stats['errors']);
        }
        
        $summary .= implode(', ', $details) . '). ';
        
        return $summary;
    }
    
    /**
     * Gère les exceptions
     */
    protected function handleException(\Exception $e): void
    {
        $this->io->error('Erreur lors de la synchronisation: ' . $e->getMessage());
        
        $this->logger->error('Erreur lors de la synchronisation', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Calcul de la durée si possible
        $duration = isset($this->startTime) ? microtime(true) - $this->startTime : 0;
        
        // Création du résumé d'erreur
        $resume = [
            'date' => date('Y-m-d H:i:s'),
            'statistiques' => [
                'total_analyses' => 0,
                'temps_execution' => round($duration, 2).'s',
                'status_command' => 'error',
                'resultats' => [
                    'status' => 'error',
                    'message' => sprintf('Une erreur est survenue lors de la synchronisation : %s', $e->getMessage()),
                    'details' => [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ],
                ],
            ],
        ];

        // Enregistrer le résumé d'erreur
        if (isset($this->dbCommand) && isset($this->execution)) {
            $logResume = new LogResume();
            $logResume->setCommand($this->dbCommand);
            $logResume->setExecutionDate($this->execution->getStartedAt());
            $logResume->setResume(json_encode($resume));
            $this->doctrine->getManager()->persist($logResume);
            
            // Mise à jour du statut de la commande
            $this->dbCommand->setLastStatus('error');
            $this->doctrine->getManager()->flush();
            
            // Finaliser la commande avec erreur
            $this->commandLogger->finishCommand($this->execution, '', 'error', $e->getMessage());
        }
    }
} 