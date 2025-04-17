<?php

namespace App\Controller;

use App\Entity\Board;
use App\Entity\Column;
use App\Repository\ColumnRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ColumnController extends AbstractController
{
    public function __construct(
        private readonly ColumnRepository $columnRepository,
        private readonly SerializerInterface $serializer,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection
    ) {}

    #[Route('/api/boards/{boardId}/columns', methods: ['GET'])]
    public function index(Board $board): JsonResponse
    {
        $columns = $this->columnRepository->findByBoardOrderedByPosition($board->getId());
        return $this->json($columns, 200, [], ['groups' => ['column:read']]);
    }

    #[Route('/api/boards/{boardId}/columns', name: 'api_columns_create', methods: ['POST'])]
    public function create(
        int $boardId,
        Request $request, 
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        $board = $entityManager->getRepository(Board::class)->find($boardId);
        
        if (!$board) {
            return $this->json(['error' => 'Board not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        $column = new Column();
        $column->setName($data['name']);
        $column->setBoard($board);
        $column->setPosition($data['position'] ?? count($board->getColumns()) + 1);
        
        $entityManager->persist($column);
        $entityManager->flush();
        
        return $this->json($column, Response::HTTP_CREATED, [], ['groups' => ['column:read']]);
    }

    #[Route('/api/boards/{boardId}/columns/{id}', name: 'api_columns_update', methods: ['PUT'])]
    public function update(
        int $boardId,
        int $id,
        Request $request, 
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        try {
            $board = $entityManager->getRepository(Board::class)->find($boardId);
            if (!$board) {
                return $this->json(['error' => 'Board not found'], Response::HTTP_NOT_FOUND);
            }

            $column = $entityManager->getRepository(Column::class)->find($id);
            if (!$column || $column->getBoard()->getId() !== $board->getId()) {
                return $this->json(['error' => 'Column not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            if (isset($data['position'])) {
                $newPosition = (int) $data['position'];
                $oldPosition = $column->getPosition();

                // Récupérer toutes les colonnes du tableau dans l'ordre
                $columns = $entityManager->getRepository(Column::class)
                    ->findBy(['board' => $board], ['position' => 'ASC']);

                // Réorganiser les positions
                foreach ($columns as $otherColumn) {
                    if ($otherColumn->getId() === $column->getId()) {
                        continue;
                    }

                    $currentPosition = $otherColumn->getPosition();

                    if ($oldPosition < $newPosition) {
                        // Déplacement vers la droite
                        if ($currentPosition > $oldPosition && $currentPosition <= $newPosition) {
                            $otherColumn->setPosition($currentPosition - 1);
                        }
                    } else {
                        // Déplacement vers la gauche
                        if ($currentPosition >= $newPosition && $currentPosition < $oldPosition) {
                            $otherColumn->setPosition($currentPosition + 1);
                        }
                    }
                }

                $column->setPosition($newPosition);
                $entityManager->flush();
            }

            // Retourner le board complet mis à jour
            $updatedColumns = $entityManager->getRepository(Column::class)
                ->findBy(['board' => $board], ['position' => 'ASC']);

            $boardData = [
                'id' => $board->getId(),
                'name' => $board->getName(),
                'description' => $board->getDescription(),
                'project' => $board->getProject(),
                'createdAt' => $board->getCreatedAt(),
                'columns' => $updatedColumns
            ];

            return $this->json($boardData, Response::HTTP_OK, [], [
                'groups' => ['board:read', 'column:read', 'card:read']
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/boards/{boardId}/columns/{id}', name: 'api_columns_delete', methods: ['DELETE'])]
    public function delete(
        int $boardId,
        int $id,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        $board = $entityManager->getRepository(Board::class)->find($boardId);
        if (!$board) {
            return $this->json(['error' => 'Board not found'], Response::HTTP_NOT_FOUND);
        }

        $column = $entityManager->getRepository(Column::class)->find($id);
        if (!$column || $column->getBoard()->getId() !== $board->getId()) {
            return $this->json(['error' => 'Column not found'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($column);
        $entityManager->flush();
        
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
} 