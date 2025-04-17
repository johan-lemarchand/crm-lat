<?php

namespace App\Applications\Praxedo\scripts\Articles\Command;

use App\Applications\Praxedo\scripts\Articles\Service\PraxedoArticleService;
use App\Applications\Wavesoft\scripts\Articles\Service\WavesoftArticleService;
use App\Command\AbstractSyncCommand;
use App\Entity\LogResume;
use App\Service\CommandLogger;
use App\Service\EmailService;
use App\Service\PraxedoApiLogger;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsCommand(
    name: 'Praxedo:articles',
    description: 'Synchronisation des articles Praxedo',
)]
class SyncArticlesCommand extends AbstractSyncCommand
{
    protected BufferedOutput $bufferedOutput;
    protected SymfonyStyle $bufferedIo;

    public function __construct(
        private readonly PraxedoArticleService $articleService,
        private readonly MailerInterface $mailer,
        private readonly WavesoftArticleService $wavesoftArticleService,
        LoggerInterface $logger,
        CommandLogger $commandLogger,
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
        return 'articles';
    }
    
    /**
     * Affichage des paramètres de la commande
     */
    protected function displayParameters(InputInterface $input): void
    {
        $this->bufferedOutput = new BufferedOutput();
        $this->bufferedIo = new SymfonyStyle($input, $this->bufferedOutput);
        
        $executionId = $this->execution->getId();
        $this->articleService->setExecutionId((string) $executionId);
        
        $message = 'Synchronisation des articles Praxedo';
        $this->bufferedOutput->writeln($message);
        $this->io->title($message);
    }

    /**
     * @throws Exception|ORMException
     */
    protected function executeSyncLogic(InputInterface $input, OutputInterface $output): array
    {
        $startTime = microtime(true);
        $wavesoftArticles = $this->wavesoftArticleService->getAllArticles();
        $this->logger->info('Articles WaveSoft récupérés', [
            'temps' => round(microtime(true) - $startTime, 3) . 's',
            'nombre' => count($wavesoftArticles)
        ]);

        $itemIds = array_column($wavesoftArticles, 'ARTCODE');

        $message = sprintf('Nombre d\'articles trouvés : %d', count($itemIds));
        $this->bufferedOutput->writeln($message);
        $this->io->info($message);

        $message = 'Matching des articles par paquets de 1000 articles maximum pour ne pas dépasser le quota praxedo de 2000 apppels par secondes';
        $this->bufferedOutput->writeln($message);
        $this->io->info($message);

        $articlesData = [];
        $chunks = array_chunk($itemIds, 95);
        $progressBar = $this->io->createProgressBar(count($chunks));
        $bufferedProgressBar = $this->bufferedIo->createProgressBar(count($chunks));
        $progressBar->start();
        $bufferedProgressBar->start();

        foreach (array_chunk($chunks, 10) as $chunksGroup) {
            $startTimeChunk = microtime(true);

            $totalDuration = 0;
            $allResponseData = [];

            foreach ($chunksGroup as $chunk) {
                $startTimeApiCall = microtime(true);
                $praxedoItems = $this->articleService->getArticles($chunk);
                $durationApiCall = microtime(true) - $startTimeApiCall;

                $totalDuration += $durationApiCall;
                $allResponseData = array_merge($allResponseData, $praxedoItems);
                $progressBar->advance();
                $bufferedProgressBar->advance();

                foreach ($praxedoItems as $praxedoItem) {
                    $itemId = $praxedoItem['id'];
                    $wavesoftData = array_filter($wavesoftArticles, function ($article) use ($itemId) {
                        return $article['ARTCODE'] === $itemId;
                    });
                    $wavesoftData = reset($wavesoftData);

                    if ($wavesoftData && (float) $praxedoItem['unitPrice'] !== (float) $wavesoftData['ATFPRIX']) {
                        $articlesData[] = [
                            'code_article' => $itemId,
                            'nom_praxedo' => $praxedoItem['name'],
                            'prix_praxedo' => $praxedoItem['unitPrice'],
                            'code_famille_praxedo' => $praxedoItem['itemCategoryId'],
                            'code_famille_wavesoft' => $wavesoftData ? $wavesoftData['AFMCODE'] : 'N/A',
                            'intitule_famille' => $wavesoftData ? $wavesoftData['AFMINTITULE'] : 'N/A',
                            'designation' => $wavesoftData ? $wavesoftData['ARTDESIGNATION'] : 'N/A',
                            'actif_wavesoft' => $wavesoftData ? ('O' === $wavesoftData['ARTISACTIF'] ? 'Oui' : 'Non') : 'N/A',
                            'prix_wavesoft' => $wavesoftData['ATFPRIX'],
                        ];
                        
                        $output->writeln(sprintf(
                            'Article %s : prix Praxedo %.2f€ -> prix Wavesoft %.2f€',
                            $itemId,
                            $praxedoItem['unitPrice'],
                            $wavesoftData['ATFPRIX']
                        ));
                    }
                }
            }

            if (!empty($allResponseData)) {
                $output->writeln(sprintf(
                    'Traitement d\'un groupe de %d articles terminé (%.2fs)',
                    count($allResponseData),
                    $totalDuration
                ));
            }

            if (!empty($allResponseData)) {
                $this->praxedoApiLogger->logApiCall(
                    (string) $this->execution->getId(),
                    '/ItemManager/getItems',
                    'POST',
                    json_encode($chunksGroup),
                    json_encode($allResponseData, JSON_PRETTY_PRINT),
                    200,
                    $totalDuration
                );
            }

            $elapsedTime = microtime(true) - $startTimeChunk;
            if ($elapsedTime < 1) {
                usleep((int) ((1 - $elapsedTime) * 1000000));
            }
        }

        $progressBar->finish();
        $bufferedProgressBar->finish();
        $this->io->newLine(2);

        $totalDuration = microtime(true) - $startTime;
        
        // Préparer les données de résultat
        $totalArticles = count($itemIds);
        $totalToUpdate = count($articlesData);
        $percentage = $totalArticles > 0 ? round(($totalToUpdate / $totalArticles) * 100, 2) : 0;
        
        $statsResults = [
            'articles' => [
                'total' => $totalArticles,
                'to_update' => $totalToUpdate,
                'percentage' => $percentage . '%',
                'data' => $articlesData,
                'errors' => 0
            ]
        ];
        
        // Affichage des résultats
        $this->io->section('Résumé de la synchronisation');
        
        $tableData = [
            ['Articles analysés', $totalArticles],
            ['Articles à mettre à jour', $totalToUpdate],
            ['Pourcentage', $percentage . '%'],
            ['Temps d\'exécution', round($totalDuration, 2) . 's'],
        ];
        
        $this->io->table(['Métrique', 'Valeur'], $tableData);
        
        if (empty($articlesData)) {
            $this->io->success('Aucun article à mettre à jour. Les prix sont synchronisés.');
        } else {
            $this->io->warning(sprintf('%d articles ont des différences de prix entre Praxedo et Wavesoft.', $totalToUpdate));
            
            // Afficher les détails des articles à mettre à jour dans un tableau
            if (!empty($articlesData)) {
                $this->io->section('Articles à mettre à jour');
                
                $headers = ['Code', 'Désignation', 'Prix Praxedo', 'Prix Wavesoft', 'Différence'];
                $rows = [];
                
                foreach ($articlesData as $article) {
                    $prixPraxedo = (float) $article['prix_praxedo'];
                    $prixWavesoft = (float) $article['prix_wavesoft'];
                    $difference = $prixWavesoft - $prixPraxedo;
                    
                    $rows[] = [
                        $article['code_article'],
                        $article['designation'],
                        number_format($prixPraxedo, 2) . '€',
                        number_format($prixWavesoft, 2) . '€',
                        number_format($difference, 2) . '€' . ($difference > 0 ? ' (+)' : ' (-)')
                    ];
                }
                
                $this->io->table($headers, $rows);
            }
        }
        
        // Appel à la fonction d'alerte par email
        $resumeForEmail = [
            'date' => date('Y-m-d H:i:s'),
            'total_articles' => $totalArticles,
            'articles' => $articlesData,
            'statistiques' => [
                'total_analyses' => $totalArticles,
                'total_a_mettre_a_jour' => $totalToUpdate,
                'pourcentage' => $percentage . '%',
                'temps_execution' => round($totalDuration, 2) . 's',
                'status_command' => 'success',
                'resultats' => [
                    'status' => 'success',
                    'message' => empty($articlesData) 
                        ? 'Aucun article à mettre à jour. Les prix sont synchronisés.'
                        : sprintf('%d articles ont des différences de prix entre Praxedo et Wavesoft.', $totalToUpdate),
                    'details' => $articlesData,
                ],
            ],
        ];
        
        $this->alerte($resumeForEmail);
        
        return $statsResults;
    }

    /**
     * Envoi d'une alerte par email
     */
    private function alerte(array $data): void
    {
        try {
            $this->emailService->sendCommandExecutionReport($data, $this->dbCommand);
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email d\'alerte', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
