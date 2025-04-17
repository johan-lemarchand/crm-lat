<?php

namespace App\Wavesoft\Application\Query\GetWavesoftLogs;

use App\Entity\WavesoftLog;
use App\Repository\WavesoftLogRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;

#[AsMessageHandler]
readonly class GetWavesoftLogsHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function __invoke(GetWavesoftLogsQuery $query): array
    {
        $limit = $query->getLimit();
        $criteria = [];
        $orderBy = ['createdAt' => 'DESC'];
        
        $logs = $this->entityManager->getRepository(WavesoftLog::class)
            ->findBy($criteria, $orderBy, $limit);

        return array_map(function (WavesoftLog $log) {
            return [
                'id' => $log->getId(),
                'automateFile' => $log->getAutomateFile(),
                'trsId' => $log->getTrsId(),
                'userName' => $log->getUserName(),
                'status' => $log->getStatus(),
                'messageError' => $log->getMessageError(),
                'aboId' => $log->getAboId(),
                'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }, $logs);
    }
} 