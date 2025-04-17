<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Repository\PasscodeRepositoryInterface;
use App\ODF\Domain\Service\TrimbleServiceInterface;
use Psr\Log\LoggerInterface;

readonly class PasscodeRepository implements PasscodeRepositoryInterface
{
    public function __construct(
        private TrimbleServiceInterface $trimbleService,
        private LoggerInterface         $logger
    ) {}

    public function getPasscodes(string $orderNumber): array
    {
        $this->logger->info('Récupération des passcodes pour la commande', [
            'orderNumber' => $orderNumber
        ]);
        
        // Appeler le service Trimble pour récupérer les activations
        $result = $this->trimbleService->getActivation($orderNumber);

        // Vérifier si la réponse contient une erreur
        if (isset($result['fault'])) {
            $this->logger->error('Erreur lors de la récupération des activations Trimble', [
                'orderNumber' => $orderNumber,
                'fault' => $result['fault']
            ]);
            return [];
        }
        
        // Vérifier si la réponse contient des activations
        if (!isset($result['activations']) || !is_array($result['activations'])) {
            $this->logger->warning('Format de réponse invalide de l\'API Trimble', [
                'orderNumber' => $orderNumber,
                'result' => $result
            ]);
            return [];
        }
        
        // Vérifier si des passcodes sont manquants
        if ($this->hasMissingPasscodes($result['activations'])) {
            $this->logger->warning('Des passcodes sont manquants dans la réponse Trimble', [
                'orderNumber' => $orderNumber
            ]);
            return [];
        }
        
        // Ajouter simplement le champ isQR à chaque activation
        foreach ($result['activations'] as &$activation) {
            if (!empty($activation['serialNumber']) && isset($activation['passcode'])) {
                $isQR = $this->checkPasscode($activation['passcode']);
                $activation['isQR'] = $isQR[1];
            }
        }
        
        return $result['activations'];
    }
    
    private function checkPasscode(string $passcode): array
    {
        $isQR = [false, "N"];
        if (str_starts_with($passcode, "l") || str_starts_with($passcode, "[") || str_ends_with($passcode, "=")) {
            $isQR[0] = true;
            $isQR[1] = "O";
        }
        return $isQR;
    }
    
    private function hasMissingPasscodes(array $activations): bool
    {
        foreach ($activations as $activation) {
            if (empty($activation['passcode']) ||
                preg_match('/^the activation/i', $activation['passcode'])) {
                return true;
            }
        }

        return false;
    }
} 