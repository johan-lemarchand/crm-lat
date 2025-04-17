<?php

namespace App\Repository;

use App\Entity\Command;
use App\Entity\CommandExecution;
use App\Service\FormatService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandExecution>
 *
 * @method CommandExecution|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommandExecution|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommandExecution[]    findAll()
 * @method CommandExecution[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandExecutionRepository extends ServiceEntityRepository
{
    private FormatService $formatService;

    public function __construct(ManagerRegistry $registry, FormatService $formatService)
    {
        parent::__construct($registry, CommandExecution::class);
        $this->formatService = $formatService;
    }

    public function getLastExecution(string $commandName): ?CommandExecution
    {
        $parts = explode(':', $commandName);
        if (2 !== count($parts)) {
            return null;
        }

        [$name, $scriptName] = $parts;

        return $this->createQueryBuilder('ce')
            ->join('ce.command', 'c')
            ->where('c.name = :name')
            ->andWhere('c.scriptName = :scriptName')
            ->setParameter('name', $name)
            ->setParameter('scriptName', $scriptName)
            ->orderBy('ce.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getCommandStats(int $commandId): array
    {
        // Récupérer toutes les exécutions pour calculer les dates
        $executions = $this->createQueryBuilder('ce')
            ->select('ce.startedAt')
            ->where('ce.command = :commandId')
            ->setParameter('commandId', $commandId)
            ->getQuery()
            ->getResult();

        $oldestDate = null;
        $newestDate = null;
        foreach ($executions as $execution) {
            $date = $execution['startedAt'];
            if (!$oldestDate || $date < $oldestDate) {
                $oldestDate = $date;
            }
            if (!$newestDate || $date > $newestDate) {
                $newestDate = $date;
            }
        }

        // Requête pour les tailles
        $qbExecution = $this->getEntityManager()->getConnection()->executeQuery('
            SELECT 
                SUM(DATALENGTH(output)) + SUM(DATALENGTH(error)) + SUM(DATALENGTH(resume)) as execution_size,
                COUNT(id) as total_logs
            FROM command_execution
            WHERE command_id = :commandId
        ', ['commandId' => $commandId])->fetchAssociative();

        $qbApi = $this->getEntityManager()->getConnection()->executeQuery('
            SELECT SUM(DATALENGTH(request_xml)) + SUM(DATALENGTH(response_xml)) as api_size
            FROM praxedo_api_log api
            JOIN command_execution ce ON ce.id = api.execution_id
            WHERE ce.command_id = :commandId
        ', ['commandId' => $commandId])->fetchAssociative();

        // Calculer la taille totale
        $totalSize = ((int) $qbExecution['execution_size'] ?: 0) + ((int) $qbApi['api_size'] ?: 0);

        $period = null;
        if ($oldestDate && $newestDate) {
            try {
                $interval = ($oldestDate->diff($newestDate) + 1);
                $period = [
                    'years' => $interval->y,
                    'months' => $interval->m,
                    'days' => $interval->d,
                ];
            } catch (\Exception $e) {
                // En cas d'erreur de calcul de l'intervalle
            }
        }

        return [
            'size' => [
                'bytes' => $totalSize,
                'formatted' => $this->formatService->formatBytes($totalSize),
                'period' => $period,
                'details' => [
                    'execution' => [
                        'bytes' => (int) $qbExecution['execution_size'] ?: 0,
                        'formatted' => $this->formatService->formatBytes((int) $qbExecution['execution_size'] ?: 0),
                    ],
                    'api' => [
                        'bytes' => (int) $qbApi['api_size'] ?: 0,
                        'formatted' => $this->formatService->formatBytes((int) $qbApi['api_size'] ?: 0),
                    ],
                ],
            ],
            'total_logs' => $qbExecution['total_logs'],
        ];
    }

    public function getStats(Command $command): array
    {
        try {
            $qb = $this->createQueryBuilder('e')
                ->select('COUNT(e.id) as total_executions')
                ->addSelect('COUNT(l.id) as total_logs')
                ->addSelect('MIN(e.startedAt) as first_execution')
                ->addSelect('MAX(e.startedAt) as last_execution')
                ->addSelect('SUM(DATALENGTH(e.output)) as output_size')
                ->addSelect('SUM(DATALENGTH(e.error)) as error_size')
                ->addSelect('SUM(DATALENGTH(l.requestXml)) as request_size')
                ->addSelect('SUM(DATALENGTH(l.responseXml)) as response_size')
                ->leftJoin('e.apiLogs', 'l')
                ->where('e.command = :command')
                ->setParameter('command', $command)
                ->groupBy('e.command');

            $result = $qb->getQuery()->getOneOrNullResult();

            // Récupérer la taille des résumés
            $qbResume = $this->getEntityManager()->createQueryBuilder()
                ->select('SUM(DATALENGTH(lr.resume)) as resume_size')
                ->from('App\Entity\LogResume', 'lr')
                ->where('lr.command = :command')
                ->setParameter('command', $command);

            $resumeResult = $qbResume->getQuery()->getOneOrNullResult();

            $executionSize = (int) ($result['output_size'] ?? 0) + (int) ($result['error_size'] ?? 0);
            $apiSize = (int) ($result['request_size'] ?? 0) + (int) ($result['response_size'] ?? 0);
            $resumeSize = (int) ($resumeResult['resume_size'] ?? 0);
            $totalSize = $executionSize + $apiSize + $resumeSize;

            $totalLogs = (int) ($result['total_logs'] ?? 0);
            $totalExecutions = (int) ($result['total_executions'] ?? 0);

            // Calculer la période
            $period = '';
            if ($result['first_execution'] instanceof \DateTime && $result['last_execution'] instanceof \DateTime) {
                $firstDate = $result['first_execution']->format('d/m/Y');
                $lastDate = $result['last_execution']->format('d/m/Y');
                $period = $firstDate === $lastDate ? $firstDate : "$firstDate - $lastDate";
            }

            return [
                'size' => [
                    'bytes' => $totalSize,
                    'formatted' => $this->formatService->formatBytes($totalSize),
                    'period' => $period,
                    'details' => [
                        'execution' => [
                            'bytes' => $executionSize,
                            'formatted' => $this->formatService->formatBytes($executionSize),
                        ],
                        'api' => [
                            'bytes' => $apiSize,
                            'formatted' => $this->formatService->formatBytes($apiSize),
                        ],
                        'resume' => [
                            'bytes' => $resumeSize,
                            'formatted' => $this->formatService->formatBytes($resumeSize),
                        ],
                    ],
                ],
                'total_logs' => $totalLogs,
                'details' => [
                    'execution' => $totalExecutions,
                    'api' => $totalLogs,
                    'period' => $period
                ]
            ];
        } catch (\Exception $e) {
            return [
                'size' => [
                    'bytes' => 0,
                    'formatted' => '0 B',
                    'period' => '',
                    'details' => [
                        'execution' => [
                            'bytes' => 0,
                            'formatted' => '0 B',
                        ],
                        'api' => [
                            'bytes' => 0,
                            'formatted' => '0 B',
                        ],
                        'resume' => [
                            'bytes' => 0,
                            'formatted' => '0 B',
                        ],
                    ],
                ],
                'total_logs' => 0,
                'details' => [
                    'execution' => 0,
                    'api' => 0,
                    'period' => ''
                ]
            ];
        }
    }

    public function getStatus(): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.status', 'COUNT(e.id) as count')
            ->groupBy('e.status');

        $results = $qb->getQuery()->getArrayResult();

        $status = [];
        foreach ($results as $result) {
            $status[$result['status']] = $result['count'];
        }

        return $status;
    }
}
