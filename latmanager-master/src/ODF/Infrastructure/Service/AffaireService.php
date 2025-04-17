<?php

namespace App\ODF\Infrastructure\Service;

use App\ODF\Domain\Repository\AffaireRepositoryInterface;
use App\ODF\Domain\Service\AffaireServiceInterface;
use App\Service\Traits\HasDebugService;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use PDO;

readonly class AffaireService implements AffaireServiceInterface
{
    public function __construct(
        private AffaireRepositoryInterface $affaireRepository
    ) {}

    public function checkAffaire(int $pcdid): array
    {
        try {
            $pieceResult = $this->affaireRepository->findAffaireByPcdid($pcdid);
            if (!$pieceResult) {
                throw new \Exception('Pièce non trouvée');
            }

            // Si une affaire est déjà associée
            if (!empty($pieceResult['AFFID'])) {
                $affaire = $this->affaireRepository->findByAffaireIdWithDetails($pieceResult['AFFID']);
                return [
                    'status' => 'success',
                    'messages' => [],
                    'affaire' => [
                        'code' => $affaire['AFFCODE']
                    ]
                ];
            }

            // Création du code affaire
            $affcode = 'APITODF_' . preg_replace('/\D/', '', $pieceResult['PCDNUM']);
            
            // Vérification si l'affaire existe déjà
            $existingAffaire = $this->affaireRepository->findAffaireByCode($affcode);
            
            if (!$existingAffaire) {
                $affIntitule = 'Commande API Trimble ' . $pieceResult['PCDNUM'];
                $affid = $this->affaireRepository->createAffaire($affcode, $affIntitule);
            } else {
                $affid = $existingAffaire['AFFID'];
            }

            $this->affaireRepository->updateAffaireLinks($pcdid, $affid);

            return [
                'status' => 'success',
                'messages' => [],
                'affaire' => [
                    'code' => $affcode,
                    'intitule' => $affIntitule ?? 'Commande API Trimble ' . $pieceResult['PCDNUM']
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'messages' => [[
                    'message' => 'Erreur lors de la gestion de l\'affaire : ' . $e->getMessage(),
                    'status' => 'error'
                ]],
                'affaire' => null
            ];
        }
    }

    public function createAffaire(string $affcode, string $affIntitule): int
    {
        return $this->affaireRepository->createAffaire($affcode, $affIntitule);
    }

    public function updateAffaireLinks(int $pcdid, int $affid): void
    {
        $this->affaireRepository->updateAffaireLinks($pcdid, $affid);
    }

    public function checkAndManageAffaire(int $pcdid): array
    {
        return $this->checkAffaire($pcdid);
    }
}
