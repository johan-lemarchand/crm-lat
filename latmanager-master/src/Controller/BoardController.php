<?php

namespace App\Controller;

use App\Entity\Board;
use App\Entity\Project;
use App\Entity\Column;
use App\Repository\BoardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api')]
class BoardController extends AbstractController
{
    public function __construct(
        private readonly BoardRepository $boardRepository,
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection
    ) {}

    #[Route('/projects/{projectId}/boards', methods: ['GET'])]
    public function index(Project $project): JsonResponse
    {
        $boards = $this->boardRepository->findBy(['project' => $project]);
        return $this->json($boards, 200, [], ['groups' => ['board:read']]);
    }

    #[Route('/boards/{id}', methods: ['GET'])]
    public function show(Board $board): JsonResponse
    {
        try {
            // On récupère les colonnes triées directement depuis le repository
            $columns = $this->entityManager->getRepository(Column::class)
                ->findBy(['board' => $board], ['position' => 'ASC']);
            
            // Au lieu de setColumns, on crée un tableau avec les données formatées
            $boardData = [
                'id' => $board->getId(),
                'name' => $board->getName(),
                'description' => $board->getDescription(),
                'project' => $board->getProject(),
                'createdAt' => $board->getCreatedAt(),
                'columns' => $columns
            ];
            
            return $this->json($boardData, 200, [], [
                'groups' => ['board:read', 'column:read', 'card:read']
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/projects/{projectId}/boards', methods: ['POST'])]
    public function create(Request $request, int $projectId): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }
        
        $board = new Board();
        $board->setName($data['name']);
        if (isset($data['description'])) {
            $board->setDescription($data['description']);
        }
        $board->setProject($project);
        $board->setCreatedAt(new \DateTimeImmutable());
        
        // Créer les colonnes fixes
        $todoColumn = new Column();
        $todoColumn->setName('À faire');
        $todoColumn->setPosition(1);
        $todoColumn->setBoard($board);
        
        $inProgressColumn = new Column();
        $inProgressColumn->setName('En cours');
        $inProgressColumn->setPosition(2);
        $inProgressColumn->setBoard($board);
        
        $doneColumn = new Column();
        $doneColumn->setName('Terminé');
        $doneColumn->setPosition(3);
        $doneColumn->setBoard($board);
        
        $this->entityManager->persist($board);
        $this->entityManager->persist($todoColumn);
        $this->entityManager->persist($inProgressColumn);
        $this->entityManager->persist($doneColumn);
        $this->entityManager->flush();
        
        return $this->json($board, 201, [], ['groups' => ['board:read']]);
    }

    #[Route('/boards/{id}', methods: ['PUT'])]
    public function update(Request $request, Board $board): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name'])) {
            $board->setName($data['name']);
        }
        if (isset($data['description'])) {
            $board->setDescription($data['description']);
        }
        
        $this->entityManager->flush();
        return $this->json($board, 200, [], ['groups' => ['board:read']]);
    }

    #[Route('/boards/{id}', methods: ['DELETE'])]
    public function delete(Board $board): JsonResponse
    {
        $this->entityManager->remove($board);
        $this->entityManager->flush();
        return $this->json(null, 204);
    }

    #[Route('/boards/{boardId}/columns/{columnId}', methods: ['PUT'])]
    public function updateColumn(
        Request $request,
        int $boardId,
        int $columnId
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $column = $this->entityManager->getRepository(Column::class)->find($columnId);
        
        if (!$column) {
            throw $this->createNotFoundException('Column not found');
        }
        
        if (isset($data['position'])) {
            $oldPosition = $column->getPosition();
            $newPosition = $data['position'];
            
            $columns = $this->entityManager->getRepository(Column::class)
                ->findBy(['board' => $boardId], ['position' => 'ASC']);
            
            foreach ($columns as $otherColumn) {
                if ($otherColumn->getId() === $column->getId()) {
                    continue;
                }

                $currentPosition = $otherColumn->getPosition();

                if ($oldPosition < $newPosition) {
                    if ($currentPosition > $oldPosition && $currentPosition <= $newPosition) {
                        $otherColumn->setPosition($currentPosition - 1);
                    }
                } else {
                    if ($currentPosition >= $newPosition && $currentPosition < $oldPosition) {
                        $otherColumn->setPosition($currentPosition + 1);
                    }
                }
            }

            $column->setPosition($newPosition);
        }
        
        if (isset($data['name'])) {
            $column->setName($data['name']);
        }
        
        $this->entityManager->flush();
        
        return $this->json([
            'id' => $column->getId(),
            'name' => $column->getName(),
            'position' => $column->getPosition()
        ]);
    }

    #[Route('/boards', methods: ['GET'])]
    public function listAll(): JsonResponse
    {
        $boards = $this->boardRepository->findAll();
        return $this->json($boards, 200, [], [
            'groups' => ['board:read', 'project:read']
        ]);
    }
} 