<?php

namespace App\Command;

use App\Service\VersionManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:version:update',
    description: 'Met à jour la version du projet ou d\'un composant spécifique',
)]
class VersionUpdateCommand extends Command
{
    public function __construct(
        private readonly VersionManager $versionManager,
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Type de mise à jour (major, minor, patch)', 'patch')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Message de commit', null)
            ->addOption('no-git', null, InputOption::VALUE_NONE, 'Ne pas exécuter les commandes Git')
            ->addOption('component', 'c', InputOption::VALUE_REQUIRED, 'Composant à mettre à jour (ex: manager_version, Praxedo_articles)', 'manager_version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = $input->getOption('type');
        $message = $input->getOption('message');
        $noGit = $input->getOption('no-git');
        $component = $input->getOption('component');

        if (!$noGit && !$message) {
            $message = $io->ask('Message de commit', null);
            if (!$message) {
                $io->error('Le message de commit est requis sauf si --no-git est utilisé');
                return Command::FAILURE;
            }
        }

        try {
            $newVersion = $this->versionManager->incrementVersion($type, $component);

            // Ajouter l'entrée dans le changelog
            $changelogType = match ($type) {
                'major' => 'Changements majeurs',
                'minor' => 'Ajouté',
                'patch' => 'Corrigé',
                default => 'Modifié',
            };
            
            $changelogMessage = sprintf('[%s] %s', $component, $message ?? 'Mise à jour de version');
            $this->versionManager->addChangelogEntry($newVersion, $changelogType, $changelogMessage);

            // Git commands
            if (!$noGit && $this->isGitRepository()) {
                $this->runGitCommands($message);
                $io->success([
                    sprintf('Version de %s mise à jour : %s', $component, $newVersion),
                    'Changelog mis à jour',
                    'Modifications commitées et poussées'
                ]);
            } else {
                $io->success([
                    sprintf('Version de %s mise à jour : %s', $component, $newVersion),
                    'Changelog mis à jour'
                ]);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function isGitRepository(): bool
    {
        return is_dir($this->projectDir . '/.git');
    }

    private function runGitCommands(string $message): void
    {
        $commands = [
            ['git', 'add', '.'],
            ['git', 'commit', '-m', $message],
            ['git', 'push']
        ];

        foreach ($commands as $command) {
            $process = new Process($command);
            $process->setWorkingDirectory($this->projectDir);
            $process->run();
        }
    }
} 