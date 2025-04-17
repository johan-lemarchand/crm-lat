<?php

namespace App\Controller;

use App\Wavesoft\Application\Query\GetWavesoftLogs\GetWavesoftLogsQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;

class WavesoftController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $queryBus
    ) {}

    #[Route('/api/wavesoft/logs', name: 'api_wavesoft_logs', methods: ['GET'])]
    public function getWavesoftLogs(Request $request): JsonResponse
    {
        $limit = $request->query->get('limit', null);
        
        try {
            $query = new GetWavesoftLogsQuery($limit);
            $envelope = $this->queryBus->dispatch($query);
            
            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();
            
            // Récupérer le log le plus récent pour afficher la dernière exécution
            $lastExecution = null;
            if (is_array($result) && count($result) > 0) {
                // Trier par date de création (le plus récent en premier)
                usort($result, function($a, $b) {
                    return $b['createdAt'] <=> $a['createdAt'];
                });
                
                $mostRecentLog = $result[0];
                $lastExecution = [
                    'date' => $mostRecentLog['createdAt'],
                    'status' => $mostRecentLog['status']
                ];
            }
            
            return $this->json([
                'status' => 'success',
                'logs' => $result,
                'lastExecution' => $lastExecution
            ]);
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des logs Wavesoft: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/wavesoft/logs/all', name: 'api_wavesoft_logs_all', methods: ['GET'])]
    public function getAllWavesoftLogs(): JsonResponse
    {
        try {
            $query = new GetWavesoftLogsQuery(null);
            $envelope = $this->queryBus->dispatch($query);
            
            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();
            
            return $this->json([
                'status' => 'success',
                'logs' => $result
            ]);
        } catch (\Exception | ExceptionInterface $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des logs Wavesoft: ' . $e->getMessage()
            ], 500);
        }
    }
} 