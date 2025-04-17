<?php

namespace App\Applications\Praxedo\scripts\Activities\Command;

use App\Applications\Praxedo\scripts\Activities\Service\PraxedoActivitiesService;
use App\Applications\Praxedo\scripts\TimeSlots\Service\PraxedoTimeSlotsService;
use App\Command\AbstractSyncCommand;
use App\Service\EmailService;
use App\Service\PraxedoApiLogger;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'Praxedo:activities',
    description: 'Synchronisation des activités et créneaux Praxedo',
)]
class SyncActivitiesCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly PraxedoActivitiesService $activitiesService,
        private readonly PraxedoTimeSlotsService $timeSlotsService,
        LoggerInterface $logger,
        \App\Service\CommandLogger $commandLogger,
        ManagerRegistry $doctrine,
        private readonly PraxedoApiLogger $praxedoApiLogger,
        private readonly EmailService $emailService,
    ) {
        parent::__construct($logger, $commandLogger, $doctrine);
    }

    /**
     * Retourne le nom de la commande pour la recherche en base
     */
    protected function getCommandName(): string
    {
        return 'Praxedo';
    }

    /**
     * Retourne le nom du script pour la recherche en base
     */
    protected function getScriptName(): string
    {
        return 'activities';
    }

    protected function configure(): void
    {
        parent::configure();
        
        $this
            ->addOption('start-date', null, InputOption::VALUE_OPTIONAL, 'Date de début (format: Y-m-d)')
            ->addOption('end-date', null, InputOption::VALUE_OPTIONAL, 'Date de fin (format: Y-m-d)')
            ->addOption('start-time', null, InputOption::VALUE_OPTIONAL, 'Heure de début UTC (format: H:i)', '00:00')
            ->addOption('end-time', null, InputOption::VALUE_OPTIONAL, 'Heure de fin UTC (format: H:i)', '23:59')
            ->addOption('skip-activities', null, InputOption::VALUE_NONE, 'Ne pas synchroniser les activités')
            ->addOption('skip-timeslots', null, InputOption::VALUE_NONE, 'Ne pas synchroniser les créneaux')
        ;
    }
    
    /**
     * Affiche les paramètres spécifiques de la commande
     */
    protected function displayParameters(InputInterface $input): void
    {
        // Gestion des dates par défaut si non fournies
        $startDateStr = $input->getOption('start-date') 
            ?? (new \DateTime('yesterday'))->format('Y-m-d');
        $endDateStr = $input->getOption('end-date') 
            ?? (new \DateTime())->format('Y-m-d');

        $startDate = new \DateTime($startDateStr);
        $endDate = new \DateTime($endDateStr);

        // Gestion des heures
        $startTime = $input->getOption('start-time') ?? '00:00';
        $endTime = $input->getOption('end-time') ?? '23:59';

        [$startHour, $startMinute] = explode(':', $startTime);
        [$endHour, $endMinute] = explode(':', $endTime);

        $startDate->setTime((int) $startHour, (int) $startMinute);
        $endDate->setTime((int) $endHour, (int) $endMinute);
        
        $executionId = $this->execution->getId();
        
        // Configurer les services avec l'ID d'exécution
        $this->activitiesService->setExecutionId((string) $executionId);
        $this->timeSlotsService->setExecutionId((string) $executionId);

        $this->io->title('Synchronisation Praxedo -> Wavesoft');
        $this->io->section('Période de synchronisation');
        $this->io->text([
            sprintf('Du : %s', $startDate->format('d/m/Y H:i:s')),
            sprintf('Au : %s', $endDate->format('d/m/Y H:i:s')),
        ]);
    }

    /**
     * Exécute la logique métier spécifique de synchronisation
     * @throws ORMException|Exception
     */
    protected function executeSyncLogic(InputInterface $input, OutputInterface $output): array
    {
        // Gestion des dates par défaut si non fournies
        $startDateStr = $input->getOption('start-date') 
            ?? (new \DateTime('yesterday'))->format('Y-m-d');
        $endDateStr = $input->getOption('end-date') 
            ?? (new \DateTime())->format('Y-m-d');

        $startDate = new \DateTime($startDateStr);
        $endDate = new \DateTime($endDateStr);

        // Gestion des heures
        $startTime = $input->getOption('start-time') ?? '00:00';
        $endTime = $input->getOption('end-time') ?? '23:59';

        [$startHour, $startMinute] = explode(':', $startTime);
        [$endHour, $endMinute] = explode(':', $endTime);

        $startDate->setTime((int) $startHour, (int) $startMinute);
        $endDate->setTime((int) $endHour, (int) $endMinute);
        
        $skipActivities = $input->getOption('skip-activities');
        $skipTimeSlots = $input->getOption('skip-timeslots');

        $statsActivities = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        $statsTimeSlots = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        // Synchronisation des activités
        if (!$skipActivities) {
            $this->io->section('Synchronisation des Activités');
            $statsActivities = $this->activitiesService->synchronizeActivities($startDate, $endDate);
        } else {
            $this->io->note('Synchronisation des activités ignorée');
        }

        // Synchronisation des créneaux
        if (!$skipTimeSlots) {
            $this->io->section('Synchronisation des Créneaux');
            $statsTimeSlots = $this->timeSlotsService->synchronizeTimeSlots($startDate, $endDate);
        } else {
            $this->io->note('Synchronisation des créneaux ignorée');
        }

        // Affichage des statistiques
        $this->io->section('Résultats de la synchronisation');

        if (!$skipActivities) {
            // Statistiques des Activités
            $this->io->table(
                ['Activités', 'Nombre'],
                [
                    ['Total analysées', $statsActivities['total']],
                    ['Créées', $statsActivities['created']],
                    ['Mises à jour', $statsActivities['updated']],
                    ['Erreurs', $statsActivities['errors']],
                ]
            );
        }

        if (!$skipTimeSlots) {
            // Statistiques des Créneaux
            $this->io->table(
                ['Créneaux', 'Nombre'],
                [
                    ['Total analysés', $statsTimeSlots['total']],
                    ['Créés', $statsTimeSlots['created']],
                    ['Mis à jour', $statsTimeSlots['updated']],
                    ['Supprimés', $statsTimeSlots['deleted']],
                    ['Erreurs', $statsTimeSlots['errors']],
                ]
            );
        }

        // Retourner les statistiques pour le résumé
        return [
            'activites' => $statsActivities,
            'creneaux' => $statsTimeSlots
        ];
    }
    
    /**
     * Surcharge la méthode pour envoyer un email après la création du résumé
     */
    protected function createAndSaveResume(array $stats, float $duration): void
    {
        // Appel à la méthode parente pour créer et sauvegarder le résumé
        parent::createAndSaveResume($stats, $duration);
        
        // Création du résumé pour l'email
        $resumeData = [
            'date' => date('Y-m-d H:i:s'),
            'statistiques' => [
                'total_analyses' => ($stats['activites']['total'] ?? 0) + ($stats['creneaux']['total'] ?? 0),
                'temps_execution' => round($duration, 2).'s',
                'status_command' => (($stats['activites']['errors'] ?? 0) === 0 && ($stats['creneaux']['errors'] ?? 0) === 0) ? 'success' : 'error',
                'activites' => $stats['activites'] ?? [],
                'creneaux' => $stats['creneaux'] ?? [],
                'resultats' => [
                    'status' => (($stats['activites']['errors'] ?? 0) === 0 && ($stats['creneaux']['errors'] ?? 0) === 0) ? 'success' : 'error',
                    'message' => sprintf(
                        'Synchronisation terminée en %.2fs.',
                        $duration
                    )
                ]
            ]
        ];
        
        // Envoi du rapport par email
        try {
            $this->emailService->sendActivitiesSyncReport($resumeData, $this->dbCommand);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi du rapport par email', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Surcharge la méthode pour gérer l'erreur et envoyer un email
     */
    protected function handleException(\Exception $e): void
    {
        // Appel à la méthode parente
        parent::handleException($e);
        
        // Création du résumé pour l'email
        $duration = isset($this->startTime) ? microtime(true) - $this->startTime : 0;
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
        
        // Envoi du rapport d'erreur par email
        try {
            if (isset($this->dbCommand)) {
                $this->emailService->sendActivitiesSyncReport($resume, $this->dbCommand);
            }
        } catch (Exception $emailException) {
            $this->logger->error('Erreur lors de l\'envoi du rapport d\'erreur par email', [
                'error' => $emailException->getMessage()
            ]);
        }
    }
}
