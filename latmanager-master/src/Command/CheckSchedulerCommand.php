<?php

namespace App\Command;

use App\Service\EmailService;
use App\Service\WindowsTaskSchedulerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;
use App\Repository\CommandRepository;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsCommand(
    name: 'Manager:check-scheduler',
    description: 'Vérifie le statut des tâches planifiées Windows',
)]
class CheckSchedulerCommand extends Command
{
    public function __construct(
        private readonly WindowsTaskSchedulerService $schedulerService,
        private readonly EmailService $emailService,
        private readonly Environment $twig,
        private readonly CommandRepository $commandRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tasks = $this->schedulerService->getAllTasks();

        $checkSchedulerCommand = $this->commandRepository->findOneBy([
            'name' => 'Manager',
            'scriptName' => 'check-scheduler'
        ]);

        if ($checkSchedulerCommand) {
            $checkSchedulerFound = false;
            foreach ($tasks as $task) {
                if ($task['folder'] === 'Manager' && $task['script'] === 'check-scheduler') {
                    $checkSchedulerFound = true;
                    $lastResult = (int) ($task['dernier résultat'] ?? 0);
                    
                    if (!empty($task['dernière exécution'])) {
                        $lastExecutionDate = \DateTime::createFromFormat('d/m/Y H:i:s', $task['dernière exécution']);
                        if ($lastExecutionDate) {
                            $checkSchedulerCommand->setLastExecutionDate($lastExecutionDate);
                        }
                    }
                    
                    if (!empty($task['prochaine exécution'])) {
                        $nextExecutionDate = \DateTimeImmutable::createFromFormat('d/m/Y H:i:s', $task['prochaine exécution']);
                        if ($nextExecutionDate) {
                            $checkSchedulerCommand->setNextExecutionDate($nextExecutionDate);
                        }
                    }
                    
                    $taskStatus = $task['statut de la tâche planifiée'] ?? '';
                    
                    $checkSchedulerCommand->setStatusScheduler($taskStatus);
                    
                    // Gestion du statut de check-scheduler
                    if ($taskStatus !== 'Activée' || $lastResult === 1) {
                        $checkSchedulerCommand->setLastStatus('ERROR');
                    } else {
                        $checkSchedulerCommand->setLastStatus('SUCCESS');
                    }
                    
                    $this->commandRepository->save($checkSchedulerCommand, true);
                    break;
                }
            }
            
            // Si la tâche n'est pas trouvée dans le planificateur
            if (!$checkSchedulerFound) {
                $checkSchedulerCommand->setLastStatus('ERROR');
                $this->commandRepository->save($checkSchedulerCommand, true);
            }
        }

        foreach ($tasks as $task) {
            if ($task['folder'] === 'Manager' && $task['script'] === 'check-scheduler') {
                continue; // On skip car déjà traité
            }
            
            $command = $this->commandRepository->findOneBy([
                'name' => $task['folder'],
                'scriptName' => $task['script']
            ]);

            if (!$command) {
                $io->warning("Aucune commande trouvée pour {$task['folder']}/{$task['script']}");
                continue;
            }

            $lastResult = (int) ($task['dernier résultat'] ?? 0);
            $status = $task['statut'] ?? '';
            $taskStatus = $task['statut de la tâche planifiée'] ?? '';

            $io->section("Tâche : {$task['folder']}/{$task['script']}");

            if (!empty($task['dernière exécution'])) {
                $lastExecutionDate = \DateTime::createFromFormat('d/m/Y H:i:s', $task['dernière exécution']);
                $command->setLastExecutionDate($lastExecutionDate);
            }
            
            $command->setStatusScheduler($taskStatus);

            if (!empty($task['prochaine exécution'])) {
                $nextExecutionDate = \DateTimeImmutable::createFromFormat('d/m/Y H:i:s', $task['prochaine exécution']);
                if ($nextExecutionDate) {
                    $command->setNextExecutionDate($nextExecutionDate);
                }
            }

            if ($taskStatus !== 'Activée') {
                $command->setActive(false);
                $io->warning("La tâche est désactivée");
                $this->commandRepository->save($command, true);
                continue;
            } else {
                $command->setActive(true);
            }

            if ($status === 'En cours') {
                $io->info("Tâche en cours d'exécution");
                $this->commandRepository->save($command, true);
                continue;
            }

            if ($status === 'Prêt' && $lastResult === 1) {
                try {
                    $this->sendErrorAlert($task, "La dernière exécution a échoué (code: $lastResult)", $command);
                } catch (LoaderError|RuntimeError|SyntaxError $e) {
                    $io->error("Erreur lors de l'envoi de l'email : {$e->getMessage()}");
                }
                $io->error("Dernier résultat : $lastResult");
                $command->setLastStatus('ERROR');
            } else {
                $command->setLastStatus('SUCCESS');
                $io->success("Tâche prête pour la prochaine exécution");
            }

            $this->commandRepository->save($command, true);
        }

        return Command::SUCCESS;
    }

    private function sendErrorAlert(array $task, string $reason, Command $command): void
    {
        $context = [
            'commandName' => $task['folder'],
            'scriptName' => $task['script'],
            'lastExecution' => $task['dernière exécution'],
            'nextExecution' => $task['prochaine exécution'],
            'lastResult' => $task['dernier résultat'],
            'status' => $task['statut de la tâche planifiée'],
            'reason' => $reason
        ];

        $emailHtml = $this->twig->render('emails/command_scheduler_alert.html.twig', $context);

        $this->emailService->sendEmail(
            "ALERTE - Problème avec la tâche {$task['folder']}/{$task['script']}",
            $emailHtml,
            null,
            null,
            null,
            true
        );
    }
}