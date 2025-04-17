<?php

namespace App\Controller;

use App\Entity\Label;
use App\Entity\Card;
use App\Repository\LabelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api')]
class LabelController extends AbstractController
{
    public function __construct(
        private readonly LabelRepository $labelRepository,
        private readonly SerializerInterface $serializer,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection
    ) {}

    #[Route('/labels', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $labels = $this->labelRepository->findAll();
        return $this->json($labels);
    }

    #[Route('/cards/{cardId}/labels', methods: ['GET'])]
    public function getCardLabels(Card $card): JsonResponse
    {
        $labels = $this->labelRepository->findByCard($card->getId());
        return $this->json($labels);
    }

    #[Route('/labels', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $label = $this->serializer->deserialize($request->getContent(), Label::class, 'json');
        $this->labelRepository->save($label);
        
        return $this->json($label, 201);
    }

    #[Route('/cards/{cardId}/labels/{labelId}', methods: ['POST'])]
    public function attachToCard(Card $card, Label $label): JsonResponse
    {
        $card->addLabel($label);
        $this->labelRepository->save($label);
        
        return $this->json($label);
    }

    #[Route('/cards/{cardId}/labels/{labelId}', methods: ['DELETE'])]
    public function detachFromCard(Card $card, Label $label): JsonResponse
    {
        $card->removeLabel($label);
        $this->labelRepository->save($label);
        
        return $this->json(null, 204);
    }

    #[Route('/labels/{id}', methods: ['PUT'])]
    public function update(Request $request, Label $label): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name'])) {
            $label->setName($data['name']);
        }
        
        if (isset($data['color'])) {
            $label->setColor($data['color']);
        }
        
        $this->labelRepository->save($label);
        
        return $this->json($label);
    }

    #[Route('/labels/{id}', methods: ['DELETE'])]
    public function delete(Label $label): JsonResponse
    {
        $this->labelRepository->remove($label);
        return $this->json(null, 204);
    }
} 