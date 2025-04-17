<?php

namespace App\Controller\Api;

use App\Service\EmailTemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/api/email-templates', name: 'api_email_templates_')]
class EmailTemplateController extends AbstractController
{
    public function __construct(
        private readonly EmailTemplateService $templateService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $templates = $this->templateService->getAllTemplates();
        
        $result = array_map(function ($template) {
            return [
                'name' => $template->getName(),
                'updatedAt' => $template->getUpdatedAt()->format('Y-m-d H:i:s')
            ];
        }, $templates);
        
        return $this->json($result);
    }

    #[Route('/{name}', name: 'get', methods: ['GET'])]
    public function get(string $name): JsonResponse
    {
        try {
            $template = $this->templateService->getTemplate($name);
            
            return $this->json([
                'name' => $template->getName(),
                'content' => $template->getContent(),
                'updatedAt' => $template->getUpdatedAt()->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/twig-template/{name}', name: 'get_twig_template', methods: ['GET'])]
    public function getTwigTemplate(string $name): Response
    {
        // Chemin vers le template Twig
        $templatePath = $this->getParameter('kernel.project_dir') . '/templates/emails/' . $name . '.html.twig';
        
        // Vérifier si le fichier existe
        if (!file_exists($templatePath)) {
            throw new NotFoundHttpException(sprintf('Template "%s" non trouvé', $name));
        }
        
        // Lire le contenu du fichier
        $content = file_get_contents($templatePath);
        
        // Retourner le contenu brut du template
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain');
        
        return $response;
    }

    #[Route('/{name}', name: 'save', methods: ['POST'])]
    public function save(string $name, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['content']) || !is_string($data['content'])) {
            return $this->json(['error' => 'Le contenu du template est requis'], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $template = $this->templateService->saveTemplate($name, $data['content']);
            
            return $this->json([
                'name' => $template->getName(),
                'updatedAt' => $template->getUpdatedAt()->format('Y-m-d H:i:s')
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{name}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $name): JsonResponse
    {
        try {
            $this->templateService->deleteTemplate($name);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{name}/preview', name: 'preview', methods: ['POST'])]
    public function preview(string $name, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data)) {
            $data = [];
        }
        
        try {
            $content = $this->templateService->renderTemplate($name, $data);
            return $this->json(['content' => $content]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
