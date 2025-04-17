<?php

namespace App\Service;

use App\Entity\CommandExecution;
use App\Repository\CommandRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CommandLogger
{
    private ?CommandExecution $currentExecution = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly CommandRepository $commandRepository,
    ) {
    }

    public function startCommand(string $commandName): CommandExecution
    {
        $parts = explode(':', $commandName);
        if (2 !== count($parts)) {
            throw new \InvalidArgumentException("Le nom de la commande doit être au format 'application:script'");
        }

        $command = $this->commandRepository->findOneBy([
            'name' => $parts[0],
            'scriptName' => $parts[1],
        ]);

        if (!$command) {
            throw new \InvalidArgumentException("Commande non trouvée : $commandName");
        }

        $execution = new CommandExecution();
        $execution->setCommand($command);
        $execution->setStartedAt(new \DateTime());
        $execution->setStatus('running');

        $this->entityManager->persist($execution);
        $this->entityManager->flush();

        $command->setLastExecutionDate(new \DateTime());
        $command->setLastStatus('running');
        $this->entityManager->flush();

        $this->currentExecution = $execution;

        return $execution;
    }

    /**
     * Log un message simple ou structuré pour une commande
     * 
     * @param string $message Message ou identifiant de commande
     * @param string|null $status Status de l'exécution (success, error, etc.)
     * @param array|null $data Données supplémentaires à logger
     */
    public function log(string $message, string $status = null, array $data = null): void
    {
        // Si status est null, alors c'est juste un message simple
        if ($status === null) {
            if ($this->currentExecution) {
                $currentOutput = $this->currentExecution->getOutput() ?? '';
                $this->currentExecution->setOutput($currentOutput . $message . "\n");
                $this->entityManager->flush();
            }
            return;
        }
        
        // Sinon c'est un log structuré (commande, status, données)
        $this->logger->info("Log de commande: $message avec statut: $status");
        
        // Juste enregistrer le message dans le log, sans toucher à l'exécution
        // pour éviter des erreurs de type
    }

    public function finishCommand(CommandExecution $execution, string $output, string $status): void
    {
        $execution->setEndedAt(new \DateTime());
        $execution->setStatus($status);

        $duration = $execution->getEndedAt()->getTimestamp() - $execution->getStartedAt()->getTimestamp();
        $execution->setDuration($duration);

        if ('success' === $status) {
            if (null === $output) {
                $execution->setOutput('Commande exécutée avec succès');
            } else {
                $execution->setOutput($output."\nCommande exécutée avec succès");
            }
            $this->logger->info('Commande exécutée avec succès');
        }

        $this->entityManager->flush();
        $this->currentExecution = null;
    }

    public function setCurrentExecution(CommandExecution $execution): void
    {
        $this->currentExecution = $execution;
    }
}
