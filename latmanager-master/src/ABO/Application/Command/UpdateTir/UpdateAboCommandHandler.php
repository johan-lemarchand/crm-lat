<?php

namespace App\ABO\Application\Command\UpdateTir;

use App\ABO\Domain\Interface\UpdateAboServiceInterface;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UpdateAboCommandHandler
{
    public function __construct(
        private UpdateAboServiceInterface $updateTirService
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(UpdateAboCommand $command): array
    {
        try {
            return $this->updateTirService->updateAbo(
                $command->getTirId(),
                $command->getTirCode(),
                $command->getTirSocieteType(),
                $command->getTirSociete()
            );
        } catch (Exception $e) {
            error_log('Erreur lors de la mise à jour TIR: ' . $e->getMessage());
            
            throw new Exception('Erreur lors de la mise à jour des informations client: ' . $e->getMessage());
        }
    }
} 