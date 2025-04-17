<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly SerializerInterface $serializer,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection
    ) {}

    #[Route('/api/projects', name: 'api_projects_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $projects = $this->projectRepository->findAll();
        return $this->json($projects, 200, [], ['groups' => ['project:read']]);
    }

    #[Route('/api/projects', name: 'api_projects_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $project = new Project();
        $project->setName($data['name']);
        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }
        
        $entityManager->persist($project);
        $entityManager->flush();
        
        return $this->json($project, Response::HTTP_CREATED, [], ['groups' => ['project:read']]);
    }

    #[Route('/api/projects/{id}', name: 'api_projects_get', methods: ['GET'])]
    public function get(Project $project): JsonResponse
    {
        return $this->json($project, 200, [], ['groups' => ['project:read', 'project:read_with_boards']]);
    }

    #[Route('/api/projects/{id}', name: 'api_projects_update', methods: ['PUT'])]
    public function update(Request $request, Project $project, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name'])) {
            $project->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $project->setDescription($data['description']);
        }
        
        $entityManager->flush();
        
        return $this->json($project, 200, [], ['groups' => ['project:read']]);
    }

    #[Route('/api/projects/{id}', name: 'api_projects_delete', methods: ['DELETE'])]
    public function delete(Project $project, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($project);
        $entityManager->flush();
        
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
} 