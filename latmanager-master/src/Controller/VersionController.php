<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class VersionController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection
    ) {}

    #[Route('/api/versions', name: 'api_versions', methods: ['GET'])]
    public function getVersions(): JsonResponse
    {
        $versionsFile = $this->getParameter('kernel.project_dir') . '/versionning/versions.json';
        
        if (!file_exists($versionsFile)) {
            return new JsonResponse(['error' => 'Fichier de versions non trouvé'], 404);
        }

        $versions = json_decode(file_get_contents($versionsFile), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Erreur lors de la lecture du fichier de versions'], 500);
        }

        return new JsonResponse($versions);
    }
}