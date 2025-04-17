<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api')]
class TokenController extends AbstractController
{
    private string $secret;
    private array $tokenConfigurations;

    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection
    ) {
        $this->secret = $_ENV['APP_SECRET'];
        
        // Configuration des différents types de tokens avec leurs paramètres requis
        $this->tokenConfigurations = [
            'default' => [
                'params' => ['id', 'pitcode', 'piccode', 'user', 'pcdnum'],
                'expiration' => 3600 // 1 heure
            ],
            'odf' => [
                'params' => ['id', 'pitcode', 'piccode', 'user', 'pcdnum'],
                'expiration' => 3600
            ],
            'abo' => [
                'params' => ['user', 'pcvnum'],
                'expiration' => 3600
            ]
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        $encoded = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $this->logger->debug('Base64URL Encode: ' . $data . ' -> ' . $encoded);
        return $encoded;
    }

    private function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    #[Route('/token/{type}', name: 'api_generate_token', defaults: ['type' => 'default'], methods: ['GET', 'POST', 'OPTIONS'])]
    public function generateToken(Request $request, string $type = 'default'): JsonResponse
    {
        // Si c'est une requête OPTIONS (preflight), on retourne juste les headers CORS
        if ($request->getMethod() === 'OPTIONS') {
            return new JsonResponse(null, 204);
        }

        // Vérifier si le type de token existe dans la configuration
        if (!isset($this->tokenConfigurations[$type])) {
            $this->logger->error('Type de token inconnu: ' . $type);
            return new JsonResponse([
                'error' => 'Type de token inconnu'
            ], 400);
        }

        // Récupérer la configuration du token
        $tokenConfig = $this->tokenConfigurations[$type];

        // Récupérer les paramètres selon la méthode HTTP
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $params = [];
            
            foreach ($tokenConfig['params'] as $param) {
                $params[$param] = $data[$param] ?? null;
            }
        } else {
            $params = [];
            
            foreach ($tokenConfig['params'] as $param) {
                $params[$param] = $request->query->get($param);
            }
        }

        // Ajouter le type de token aux paramètres
        $params['type'] = $type;

        // Vérifier que tous les paramètres requis sont présents
        if (in_array(null, $params, true)) {
            $this->logger->error('Paramètres manquants:', $params);
            return new JsonResponse([
                'error' => 'Paramètres manquants',
                'received_params' => $params,
                'required_params' => $tokenConfig['params'],
                'request_content' => $request->getContent(),
                'method' => $request->getMethod()
            ], 400);
        }

        // Ajouter une expiration
        $params['exp'] = time() + $tokenConfig['expiration'];

        // Encoder les paramètres
        $data = $this->base64UrlEncode(json_encode($params));
        
        // Générer la signature
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
        
        // Créer le token (data.signature)
        $token = $data . '.' . $signature;

        // Retourner le token en JSON
        return new JsonResponse([
            'token' => $token,
            'type' => $type,
            'expires_in' => $tokenConfig['expiration']
        ]);
    }

    #[Route('/token/check/verify', name: 'api_verify_token', methods: ['GET'])]
    public function verifyToken(Request $request): JsonResponse
    {
        $token = urldecode($request->query->get('token'));
        if (!$token) {
            return new JsonResponse(['error' => 'Token manquant'], 401);
        }

        $this->logger->debug('Verifying token: ' . $token);

        // Séparer les données et la signature
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            $this->logger->error('Invalid token format');
            return new JsonResponse(['error' => 'Token invalide'], 401);
        }

        [$data, $signature] = $parts;

        // Vérifier la signature
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
        
        $this->logger->debug('Signature check:', [
            'received' => $signature,
            'expected' => $expectedSignature
        ]);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->error('Invalid signature');
            return new JsonResponse(['error' => 'Signature invalide'], 401);
        }

        // Décoder les données
        $params = json_decode($this->base64UrlDecode($data), true);
        if (!$params) {
            $this->logger->error('Invalid data format');
            return new JsonResponse(['error' => 'Données invalides'], 401);
        }
        
        // Vérifier l'expiration
        if (isset($params['exp']) && $params['exp'] < time()) {
            $this->logger->error('Token expired');
            return new JsonResponse(['error' => 'Token expiré'], 401);
        }
        
        // Vérifier le type du token
        $tokenType = $params['type'] ?? 'default';
        if (!isset($this->tokenConfigurations[$tokenType])) {
            $this->logger->error('Type de token inconnu dans le token vérifié: ' . $tokenType);
            return new JsonResponse(['error' => 'Type de token inconnu'], 401);
        }

        // Retourner la validité et les informations du token
        return new JsonResponse([
            'valid' => true,
            'type' => $tokenType,
            'expires_at' => $params['exp'] ?? null,
            'data' => $params  // Ajouter les données décodées du token
        ]);
    }
}
