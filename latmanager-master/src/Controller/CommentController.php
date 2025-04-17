<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Card;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api/cards/{cardId}/comments')]
class CommentController extends AbstractController
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly SerializerInterface $serializer,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(Card $card): JsonResponse
    {
        $comments = $this->commentRepository->findByCardOrderedByDate($card->getId());
        return $this->json($comments);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, Card $card): JsonResponse
    {
        $comment = $this->serializer->deserialize($request->getContent(), Comment::class, 'json');
        $comment->setCard($card);
        $comment->setCreatedAt(new \DateTimeImmutable());
        
        $this->commentRepository->save($comment);
        
        return $this->json($comment, 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Request $request, Card $card, Comment $comment): JsonResponse
    {
        if ($comment->getCard() !== $card) {
            throw $this->createNotFoundException();
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['content'])) {
            $comment->setContent($data['content']);
            $comment->setUpdatedAt(new \DateTimeImmutable());
        }
        
        $this->commentRepository->save($comment);
        
        return $this->json($comment);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Card $card, Comment $comment): JsonResponse
    {
        if ($comment->getCard() !== $card) {
            throw $this->createNotFoundException();
        }

        $this->commentRepository->remove($comment);
        return $this->json(null, 204);
    }
} 