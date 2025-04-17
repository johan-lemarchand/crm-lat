<?php

namespace App\Applications\Praxedo\scripts\Clients\Command;

use AllowDynamicProperties;
use App\Applications\Praxedo\scripts\Clients\Service\PraxedoClientsService;
use App\Applications\Wavesoft\scripts\clients\Service\WavesoftClientsService;
use App\Command\AbstractSyncCommand;
use App\Service\CommandLogger;
use App\Utils\SyncConstants;
use App\Utils\SyncDateManager;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AllowDynamicProperties] #[AsCommand(
    name: 'Praxedo:clients',
    description: 'Synchronisation des clients depuis Wavesoft vers Praxedo',
)]
class SyncClientsCommand extends AbstractSyncCommand
{
    public function __construct(
        private readonly PraxedoClientsService $praxedoClientsService,
        private readonly WavesoftClientsService $wavesoftClientsService,
        LoggerInterface $logger,
        CommandLogger $commandLogger,
        ManagerRegistry $doctrine,
        private readonly SyncDateManager $syncDateManager,
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
        return 'clients';
    }

    protected function configure(): void
    {
        parent::configure();
        
        $this
            ->addOption('date', 'd', InputOption::VALUE_OPTIONAL, 'Date de dernière mise à jour au format Y-m-d H:i:s')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la synchronisation même si dernière exécution récente')
            ->addOption('test', 't', InputOption::VALUE_NONE, 'Mode test: ne traite que le client avec le code 0008613')
        ;
    }
    
    /**
     * Affiche les paramètres spécifiques de la commande
     */
    protected function displayParameters(InputInterface $input): void
    {
        // Déterminer la date de dernière mise à jour
        $dateUpdateClients = $input->getOption('date');
        if (!$dateUpdateClients) {
            $dateUpdateClients = $this->syncDateManager->getLastSyncDate(SyncConstants::CLIENTS_PRAXEDO);
        }
        
        $executionId = $this->execution->getId();
        
        $this->io->title('Synchronisation des clients depuis Wavesoft vers Praxedo');
        $this->io->section('Paramètres');
        $this->io->table(
            ['Paramètre', 'Valeur'],
            [
                ['Date de dernière mise à jour', $dateUpdateClients],
                ['ID Exécution', $executionId],
                ['Mode test', $input->getOption('test') ? 'Oui' : 'Non']
            ]
        );
        
        // Configurer l'ID d'exécution pour le service clients
        $this->praxedoClientsService->setExecutionId($executionId);
    }

    /**
     * Exécute la logique métier spécifique de synchronisation des clients
     * @throws Exception
     */
    protected function executeSyncLogic(InputInterface $input, OutputInterface $output): array
    {
        // Déterminer la date de dernière mise à jour
        $dateUpdateClients = $input->getOption('date');
        if (!$dateUpdateClients) {
            $dateUpdateClients = $this->syncDateManager->getLastSyncDate(SyncConstants::CLIENTS_PRAXEDO);
        }
        
        // Récupérer les clients mis à jour depuis Wavesoft
        $this->io->section('Récupération des clients depuis Wavesoft');

        $clients = $this->wavesoftClientsService->getUpdatedClients($dateUpdateClients);

        // Si mode test, ne traiter que le client avec le code 0008613
        if ($input->getOption('test')) {
            $filteredClients = [];
            foreach ($clients as $client) {
                if ($client['Code'] === '0008613') {
                    $filteredClients[] = $client;
                    break;
                }
            }
            
            if (empty($filteredClients)) {
                $this->io->warning('Le client test avec le code 0008613 n\'a pas été trouvé dans les clients à synchroniser');
                return ['clients' => ['total' => 0, 'created_customers' => 0, 'created_locations' => 0, 'errors' => 0]];
            }
            
            $clients = $filteredClients;
            $this->io->info('Mode test activé: synchronisation uniquement du client 0008613');
        }
        
        if (empty($clients)) {
            $this->io->success('Aucun client à synchroniser');
            return ['clients' => ['total' => 0, 'created_customers' => 0, 'created_locations' => 0, 'errors' => 0]];
        }
        
        $this->io->info(sprintf('Nombre de clients à synchroniser : %d', count($clients)));

        // Synchroniser les clients vers Praxedo
        $this->io->section('Synchronisation des clients vers Praxedo');
        $stats = $this->praxedoClientsService->synchronizeClients($clients);

        // Mettre à jour la date de dernière synchronisation
        $this->syncDateManager->updateLastSyncDate(SyncConstants::CLIENTS_PRAXEDO);
        
        // Afficher les statistiques
        $this->io->section('Statistiques de synchronisation');
        $this->io->table(
            ['Métrique', 'Valeur'],
            [
                ['Total clients', $stats['total']],
                ['Clients créés', $stats['created_customers']],
                ['Emplacements créés', $stats['created_locations']],
                ['Erreurs', $stats['errors']],
            ]
        );
        
        if ($stats['errors'] > 0 && !empty($stats['error_details'])) {
            $this->io->section('Détails des erreurs');
            foreach ($stats['error_details'] as $error) {
                $this->io->error($error['error']);
            }
        }
        
        // Formater les résultats pour le rapport
        return [
            'clients' => [
                'total' => $stats['total'],
                'created_customers' => $stats['created_customers'],
                'created_locations' => $stats['created_locations'],
                'errors' => $stats['errors'],
                'error_details' => $stats['error_details'] ?? []
            ]
        ];
    }
} 