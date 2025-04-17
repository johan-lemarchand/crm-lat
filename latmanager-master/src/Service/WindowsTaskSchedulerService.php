<?php

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

readonly class WindowsTaskSchedulerService
{
    public function __construct(
        private string $projectDir,
    ) {}

    public function getAllTasks(): array
    {
        try {
            $process = new Process(['C:\Windows\System32\schtasks.exe', '/query', '/fo', 'csv', '/v']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            $lines = str_getcsv($output, "\n");
            if (count($lines) < 2) {
                return [];
            }

            $headers = str_getcsv($lines[0]);
            $tasks = [];

            for ($i = 1; $i < count($lines); $i++) {
                $values = str_getcsv($lines[$i]);
                if (count($headers) === count($values)) {
                    $taskData = array_combine($headers, $values);
                    
                    if (str_contains($taskData['Nom de la tâche'] ?? '', '\LATMANAGER')) {
                        $fullName = $taskData['Nom de la tâche'] ?? '';
                        $pathWithoutPrefix = str_replace('\LATMANAGER\\', '', $fullName);
                        
                        $parts = explode('\\', $pathWithoutPrefix);
                        
                        $folder = $parts[0] ?? '';
                        $scriptName = end($parts) ?? '';
                        $resultCode = $taskData['Dernier résultat'] ?? '';
                        
                        if ($resultCode === '267011') {
                            $taskData['Statut'] = 'WARNING';
                        }
                        
                        $tasks[] = [
                            'folder' => $folder,
                            'script' => $scriptName,
                            'dernière exécution' => $taskData['Heure de la dernière exécution'] ?? '',
                            'prochaine exécution' => $taskData['Prochaine exécution'] ?? '',
                            'dernier résultat' => $resultCode,
                            'statut' => $taskData['Statut'] ?? '',
                            'statut de la tâche planifiée' => $taskData['Statut de la tâche planifiée'] ?? '',
                        ];
                    }
                }
            }

            return $tasks;
        } catch (\Exception $e) {
            dump($e->getMessage());
            return [];
        }
    }
} 