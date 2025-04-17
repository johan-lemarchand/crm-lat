<?php

namespace App\Command\Application\Command\CheckScheduler;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CheckSchedulerHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository,
        private EmailService $emailService
    ) {}

    public function __invoke(CheckSchedulerCommand $command): array
    {
        $scriptCommand = $this->commandRepository->find($command->getId());
        
        if (!$scriptCommand) {
            throw new \RuntimeException('Command not found');
        }

        if ($command->getEmailHtml()) {
            $this->emailService->sendSchedulerStatusEmail($scriptCommand, $command->getEmailHtml());
        }

        return [
            'message' => 'Vérification du statut du scheduler effectuée avec succès',
            'command' => [
                'id' => $scriptCommand->getId(),
                'name' => $scriptCommand->getName(),
                'lastExecutionDate' => $scriptCommand->getLastExecutionDate()?->format('Y-m-d H:i:s'),
                'attemptMax' => $scriptCommand->getAttemptMax(),
            ],
        ];
    }
} 