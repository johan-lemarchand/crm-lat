<?php

namespace App\Command\Application\Command\ExecuteCommand;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Service\CommandLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsMessageHandler]
readonly class ExecuteCommandHandler
{
    private string $projectDir;
    
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private CommandLogger $commandLogger,
        private LoggerInterface $logger,
        private ParameterBagInterface $parameterBag
    ) {
        $this->projectDir = $this->parameterBag->get('kernel.project_dir');
    }

    public function __invoke(ExecuteCommandCommand $command): array
    {
        try {
            $scriptCommand = $this->commandRepository->find($command->getId());

            if (!$scriptCommand) {
                $this->logger->error('Command not found', ['command_id' => $command->getId()]);
                return [
                    'status' => 'error',
                    'message' => 'Commande non trouvée'
                ];
            }
            
            $scriptCommand->setManualExecutionDate(new \DateTimeImmutable());
            $this->commandRepository->save($scriptCommand);

            $fullScriptName = sprintf(
                '%s:%s',
                strtolower($scriptCommand->getName()),
                strtolower($scriptCommand->getScriptName())
            );
            
            $cmd = sprintf(
                'php %s/bin/console %s --no-interaction',
                $this->projectDir,
                $fullScriptName
            );
            
            $parameters = $command->getParameters();
            $commandParameters = '';
            
            if (!empty($parameters)) {
                foreach ($parameters as $key => $value) {
                    if (is_bool($value)) {
                        if ($value) {
                            $commandParameters .= sprintf(' --%s', $key);
                        }
                    } else {
                        $commandParameters .= sprintf(' --%s="%s"', $key, $value);
                    }
                }
            }
            
            $cmd .= $commandParameters;

            $this->logger->info('Executing command', [
                'script_name' => $fullScriptName,
                'project_dir' => $this->projectDir,
                'command' => $cmd
            ]);

            $process = Process::fromShellCommandline($cmd);
            $process->setWorkingDirectory($this->projectDir);
            $process->setTimeout(3600);
            $process->run(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    $this->logger->error('Process error output: ' . $buffer);
                } else {
                    $this->logger->info('Process output: ' . $buffer);
                }
            });

            try {
                $this->commandLogger->log($scriptCommand->getScriptName());
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to log command execution', [
                    'error' => $e->getMessage(),
                    'command' => $scriptCommand->getScriptName()
                ]);
            }

            if (!$process->isSuccessful()) {
                $errorMessage = $process->getErrorOutput() ?: $process->getOutput() ?: 'Une erreur est survenue lors de l\'exécution de la commande';
                $this->logger->error('Command execution failed', [
                    'error' => $errorMessage,
                    'exit_code' => $process->getExitCode(),
                    'command' => $fullScriptName
                ]);
                
                return [
                    'status' => 'error',
                    'message' => $errorMessage
                ];
            }

            return [
                'status' => 'success',
                'output' => $process->getOutput()
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during command execution', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'Une erreur inattendue est survenue: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Exécute une commande avec streaming de sortie en temps réel
     * 
     * @param ExecuteCommandCommand $command Le message de commande
     * @param callable $callback La fonction de callback pour le streaming
     */
    public function executeWithStreaming(ExecuteCommandCommand $command, callable $callback): void
    {
        // Stocker toute la sortie
        $outputBuffer = '';
        
        try {
            $scriptCommand = $this->commandRepository->find($command->getId());

            if (!$scriptCommand) {
                $this->logger->error('Command not found', ['command_id' => $command->getId()]);
                $callback([
                    'status' => 'error',
                    'message' => 'Commande non trouvée'
                ]);
                return;
            }
            
            $scriptCommand->setManualExecutionDate(new \DateTimeImmutable());
            $this->commandRepository->save($scriptCommand);

            $fullScriptName = sprintf(
                '%s:%s',
                strtolower($scriptCommand->getName()),
                strtolower($scriptCommand->getScriptName())
            );
            
            $cmd = sprintf(
                'php %s/bin/console %s --no-interaction',
                $this->projectDir,
                $fullScriptName
            );
            
            // Traiter les paramètres
            $parameters = $command->getParameters();
            $commandParameters = '';
            
            if (!empty($parameters)) {
                foreach ($parameters as $key => $value) {
                    if (is_bool($value)) {
                        if ($value) {
                            $commandParameters .= sprintf(' --%s', $key);
                        }
                    } else {
                        $commandParameters .= sprintf(' --%s="%s"', $key, $value);
                    }
                }
            }
            
            // Ajouter les paramètres à la commande
            $cmd .= $commandParameters;

            $this->logger->info('Executing command with streaming', [
                'script_name' => $fullScriptName,
                'project_dir' => $this->projectDir,
                'command' => $cmd
            ]);

            $process = Process::fromShellCommandline($cmd);
            $process->setWorkingDirectory($this->projectDir);
            $process->setTimeout(3600);
            
            $process->start();
            
            foreach ($process as $type => $data) {
                $this->logger->info(($type === Process::ERR ? 'ERR: ' : 'OUT: ') . $data);
                
                // Ajouter au buffer
                $outputBuffer .= $data;
                
                // Envoyer en streaming
                $callback([
                    'output' => $data
                ]);
            }
            
            $process->wait();
            
            // Enregistrer le journal d'exécution
            try {
                $this->commandLogger->log($scriptCommand->getScriptName());
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to log command execution', [
                    'error' => $e->getMessage(),
                    'command' => $scriptCommand->getScriptName()
                ]);
            }
            
            // Envoyer le statut final
            $status = $process->isSuccessful() ? 'success' : 'error';
            $finalOutput = $process->isSuccessful() ? $outputBuffer : $process->getErrorOutput();
            
            $callback([
                'status' => $status,
                'output' => $finalOutput
            ]);
                
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during command execution', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $callback([
                'status' => 'error',
                'message' => 'Une erreur inattendue est survenue: ' . $e->getMessage(),
                'output' => $outputBuffer // Inclure la sortie dans le message d'erreur
            ]);
        }
    }
} 