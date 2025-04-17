<?php

namespace App\ABO\Application\Command\CheckAbo;

use App\ABO\Domain\Interface\CheckAboRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CheckAboHandler
{
    public function __construct(
        private CheckAboRepositoryInterface $checkAboRepository
    ) {}

    public function __invoke(CheckAboCommand $command): array
    {
        try {
            $pcvnum = $command->getPcvnum();
            return $this->checkAboRepository->findByPcvnum($pcvnum);

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