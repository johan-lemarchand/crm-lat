<?php

namespace App\Controller;

use App\Entity\Card;
use App\Entity\Column;
use App\Repository\CardRepository;
use App\Repository\ColumnRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CardController extends AbstractController
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly ColumnRepository $columnRepository,
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection
    ) {}

    #[Route('/api/columns/{columnId}/cards', methods: ['GET'])]
    public function index(Column $column): JsonResponse
    {
        $cards = $this->cardRepository->findByColumnOrderedByPosition($column->getId());
        return $this->json($cards);
    }

    #[Route('/api/columns/{columnId}/cards', methods: ['POST'])]
    public function create(
        Request $request, 
        int $columnId,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $column = $entityManager->getRepository(Column::class)->find($columnId);
            if (!$column) {
                return $this->json(['error' => 'Column not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            
            $card = new Card();
            $card->setTitle($data['title']);
            if (isset($data['description'])) {
                $card->setDescription($data['description']);
            }
            $card->setColumn($column);
            
            // Définir la position à la fin par défaut
            $lastPosition = $this->cardRepository->findLastPositionInColumn($column->getId());
            $card->setPosition($lastPosition + 1);
            
            $card->setCreatedAt(new \DateTimeImmutable());
            $card->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->persist($card);
            $entityManager->flush();

            return $this->returnBoardData($column->getBoard());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/columns/{columnId}/cards/{id}', methods: ['PUT'])]
    public function update(
        int $columnId,
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        try {
            $card = $entityManager->getRepository(Card::class)->find($id);
            if (!$card) {
                return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $sourceColumn = $card->getColumn();
            $oldPosition = $card->getPosition();

            // Mise à jour des champs simples
            if (isset($data['title'])) {
                $card->setTitle($data['title']);
            }
            if (isset($data['description'])) {
                $card->setDescription($data['description']);
            }

            // Si on change de colonne
            if (isset($data['columnId']) && $sourceColumn->getId() !== (int)$data['columnId']) {
                $targetColumn = $entityManager->getRepository(Column::class)->find($data['columnId']);
                if (!$targetColumn) {
                    return $this->json(['error' => 'Target column not found'], Response::HTTP_NOT_FOUND);
                }

                // Déplacer la carte vers la nouvelle colonne
                $card->setColumn($targetColumn);
                
                // Mettre à jour les positions dans l'ancienne colonne
                $this->updatePositionsAfterRemoval($sourceColumn, $oldPosition);
                
                // Mettre la carte à la position spécifiée ou à la fin de la nouvelle colonne
                $newPosition = isset($data['position']) ? (int)$data['position'] : $this->cardRepository->findLastPositionInColumn($targetColumn->getId()) + 1;
                $this->updatePositionsForInsertion($targetColumn, $newPosition);
                $card->setPosition($newPosition);
            }
            // Si on réarrange dans la même colonne
            elseif (isset($data['position'])) {
                $newPosition = (int)$data['position'];
                if ($newPosition !== $oldPosition) {
                    $this->updatePositionsForReorder($sourceColumn, $oldPosition, $newPosition);
                    $card->setPosition($newPosition);
                }
            }

            $card->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            return $this->returnBoardData($sourceColumn->getBoard());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/columns/{columnId}/cards/{id}', methods: ['DELETE'])]
    public function delete(Column $column, Card $card): JsonResponse
    {
        if ($card->getColumn() !== $column) {
            throw $this->createNotFoundException();
        }

        $oldPosition = $card->getPosition();
        $this->updatePositionsAfterRemoval($column, $oldPosition);
        
        $this->entityManager->remove($card);
        $this->entityManager->flush();
        
        return $this->returnBoardData($column->getBoard());
    }

    private function updatePositionsAfterRemoval(Column $column, int $oldPosition): void
    {
        $cards = $this->cardRepository->findBy(['column' => $column], ['position' => 'ASC']);
        foreach ($cards as $card) {
            if ($card->getPosition() > $oldPosition) {
                $card->setPosition($card->getPosition() - 1);
            }
        }
    }

    private function updatePositionsForInsertion(Column $column, int $position): void
    {
        $cards = $this->cardRepository->findBy(['column' => $column], ['position' => 'ASC']);
        foreach ($cards as $card) {
            if ($card->getPosition() >= $position) {
                $card->setPosition($card->getPosition() + 1);
            }
        }
    }

    private function updatePositionsForReorder(Column $column, int $oldPosition, int $newPosition): void
    {
        $cards = $this->cardRepository->findBy(['column' => $column], ['position' => 'ASC']);
        if ($oldPosition < $newPosition) {
            // Déplacement vers le bas
            foreach ($cards as $card) {
                $pos = $card->getPosition();
                if ($pos > $oldPosition && $pos <= $newPosition) {
                    $card->setPosition($pos - 1);
                }
            }
        } else {
            // Déplacement vers le haut
            foreach ($cards as $card) {
                $pos = $card->getPosition();
                if ($pos >= $newPosition && $pos < $oldPosition) {
                    $card->setPosition($pos + 1);
                }
            }
        }
    }

    private function returnBoardData($board): JsonResponse
    {
        $updatedColumns = $this->entityManager->getRepository(Column::class)
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
    }
} 