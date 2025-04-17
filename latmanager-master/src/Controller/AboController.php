<?php

namespace App\Controller;

use App\ABO\Application\Command\CheckAbo\CheckAboCommand;
use App\ABO\Application\Command\CheckCodeClient\CheckCodeClientCommand;
use App\ABO\Application\Command\CreateAbo\CreateAboCommand;
use App\ABO\Application\Query\GetEnumTypes\GetEnumTypesQuery;
use App\ABO\Application\Query\GetPctcode\GetPctcodeQuery;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;

class AboController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private readonly Connection $connection,
    ) {}

    #[Route('/api/abo/check', name: 'api_abo_check', methods: ['GET'])]
    public function check(Request $request): JsonResponse
    {
        $pcvnum = $request->query->get('pcvnum');
        $user = $request->query->get('user');

        try {
            $command = new CheckAboCommand(
                pcvnum: $pcvnum,
                user: $user
            );

            $envelope = $this->commandBus->dispatch($command);

            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();

            if (empty($result)) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Pas d\'abonnement trouvé'
                ]);
            }
            return $this->json([
                'status' => 'success',
                'message' => 'Récupération réussie',
                'details' => $result
            ]);
        } catch (\Exception | ExceptionInterface $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/abo/check-code-client', name: 'api_abo_check_code_client', methods: ['GET'])]
    public function checkCodeClient(Request $request): JsonResponse
    {
        $user = $request->query->get('user');
        $codeClient = $request->query->get('codeClient');
        $pcvnum = $request->query->get('pcvnum');
        $type = $request->query->get('type');

        try {
            $command = new CheckCodeClientCommand(
                user: $user,
                codeClient: $codeClient,
                pcvnum: $pcvnum,
                type: $type
            );

            $envelope = $this->commandBus->dispatch($command);

            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();
            
            // Vérifie si le code client existe
            $exists = !empty($result);
            
            if (!$exists) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le code client n\'existe pas'
                ]);
            }

            return $this->json([
                'success' => true,
                'details' => $result
            ]);
        } catch (\Exception | ExceptionInterface $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la vérification du code client : ' . $e->getMessage()
            ], 500);
        }
    }
    #[Route('/api/abo/get-enum-types', name: 'api_abo_get_enum_types', methods: ['GET'])]
    public function getEnumTypes(Request $request): JsonResponse
    {
        try {
            $type = $request->query->get('type');

            $query = new GetEnumTypesQuery(
                type: $type
            );

            $envelope = $this->commandBus->dispatch($query);

            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();

            return $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception | ExceptionInterface $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la récupération des énumérations : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/abo/get-pctcode', name: 'api_abo_get_pctcode', methods: ['GET'])]
    public function getPctcode(Request $request): JsonResponse
    {
        try {
            $query = new GetPctcodeQuery();
            
            $envelope = $this->commandBus->dispatch($query);
            
            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();
            
            return $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception | ExceptionInterface $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la récupération des codes de transformation : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/abo/create', name: 'api_abo_create', methods: ['POST'])]
    public function createAbo(Request $request): JsonResponse
    {
        try {
            // Récupérer et décoder les données JSON du corps de la requête
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Données invalides ou mal formatées'
                ], 400);
            }
            
            // Vérifier que tous les tableaux nécessaires sont présents
            if (!isset($data['pcvnum']) || !isset($data['user']) ||
                !isset($data['tableauPrincipal']) || !isset($data['automateE']) || !isset($data['automateAB']) ||
                !isset($data['automateAF']) || !isset($data['automateAL']) || !isset($data['automateAA']) ||
                !isset($data['automateAE'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Données incomplètes pour la création de l\'abonnement'
                ], 400);
            }
            
            $command = new CreateAboCommand(
                pcvnum: $data['pcvnum'],
                user: $data['user'],
                lignes: $data['tableauPrincipal'],
                automateE: $data['automateE'],
                automateAB: $data['automateAB'],
                automateAF: $data['automateAF'],
                automateAL: $data['automateAL'],
                automateAA: $data['automateAA'],
                automateAE: $data['automateAE'],
                memoId: $data['memoId'] ?? null
            );
            
            $envelope = $this->commandBus->dispatch($command);
            
            /** @var HandledStamp $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            $result = $stamp?->getResult();
            
            if (isset($result['success']) && $result['success']) {
                return $this->json([
                    'success' => true,
                    'message' => 'Abonnement créé avec succès',
                    'details' => $result
                ]);
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'abonnement',
                'details' => $result ?? null
            ]);
            
        } catch (\Exception | ExceptionInterface $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'abonnement : ' . $e->getMessage()
            ], 500);
        }
    }
}