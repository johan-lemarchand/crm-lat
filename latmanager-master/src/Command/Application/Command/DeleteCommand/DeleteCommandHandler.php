<?php

namespace App\Command\Application\Command\DeleteCommand;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeleteCommandHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository
    ) {}

    public function __invoke(DeleteCommandCommand $command): array
    {
        $existingCommand = $this->commandRepository->find($command->getId());
        
        if (!$existingCommand) {
            throw new \RuntimeException('Command not found');
        }

        $this->commandRepository->remove($existingCommand);

        return ['message' => 'Commande supprimée avec succès'];
    }
} 