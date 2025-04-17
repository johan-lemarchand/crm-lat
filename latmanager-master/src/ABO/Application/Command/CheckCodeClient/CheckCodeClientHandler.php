<?php

namespace App\ABO\Application\Command\CheckCodeClient;

use App\ABO\Domain\Interface\CheckCodeClientRepositoryInterface;
use App\ABO\Domain\Interface\UpdateAboRepositoryInterface;
use App\ABO\Domain\Interface\CheckAboRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CheckCodeClientHandler
{
    public function __construct(
        private CheckCodeClientRepositoryInterface $checkAboRepository,
        private UpdateAboRepositoryInterface $UpdateAboRepositoryInterface,
        private CheckAboRepositoryInterface $checkAboDetailsRepository
    ) {}

    public function __invoke(CheckCodeClientCommand $command): array
    {
        try {
            $codeClient = $command->getCodeClient();
            $result = $this->checkAboRepository->findCodeClient($codeClient);

            if (empty($result)) {
                return [];
            }

            if (!isset($result[0]['TIRID'])) {
                throw new \Exception('TIRID non trouvé pour ce code client');
            }

            // Switch sur le type pour appeler la bonne fonction
            switch ($command->getType()) {
                case 'final':
                    return $this->checkAboDetailsRepository->findAdrNewCodeFinalClient($result[0]['TIRID']);
                case 'facture':
                    return $this->checkAboDetailsRepository->findAdrNewCodeClientInvoice($result[0]['TIRID']);
                case 'livre':
                    return $this->checkAboDetailsRepository->findAdrNewCodeClientDelivery($result[0]['TIRID']);
                default:
                    throw new \Exception('Type de client non reconnu');
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'messages' => [[
                    'type' => 'error',
                    'message' => 'Erreur lors de la vérification du BLC : ' . $e->getMessage(),
                    'title' => 'Vérification du BLC'
                ]]
            ];
        }
    }
} 