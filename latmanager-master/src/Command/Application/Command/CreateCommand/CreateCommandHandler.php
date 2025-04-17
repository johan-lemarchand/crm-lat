<?php

namespace App\Command\Application\Command\CreateCommand;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use App\Entity\Command;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CreateCommandHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository
    ) {}

    /**
     * @throws \DateMalformedStringException
     */
    public function __invoke(CreateCommandCommand $command): array
    {
        $newCommand = new Command();
        $newCommand->setName($command->getName());
        $newCommand->setScriptName($command->getScriptName());
        $newCommand->setStartTime($command->getStartTime() ? new \DateTimeImmutable($command->getStartTime()) : null);
        $newCommand->setEndTime($command->getEndTime() ? new \DateTimeImmutable($command->getEndTime()) : null);
        $newCommand->setRecurrence($command->getRecurrence());
        $newCommand->setActive(true);
        $newCommand->setStatusSendEmail($command->getStatusSendEmail());

        if ($command->getInterval() !== null) {
            $newCommand->setInterval($command->getInterval());
        }
        if ($command->getAttemptMax() !== null) {
            $newCommand->setAttemptMax($command->getAttemptMax());
        }

        $this->commandRepository->save($newCommand);

        return [
            'message' => 'Commande créée avec succès',
            'command' => [
                'id' => $newCommand->getId(),
                'name' => $newCommand->getName(),
                'scriptName' => $newCommand->getScriptName(),
                'startTime' => $newCommand->getStartTime()?->format('H:i'),
                'endTime' => $newCommand->getEndTime()?->format('H:i'),
                'recurrence' => $newCommand->getRecurrence(),
                'active' => $newCommand->isActive(),
                'interval' => $newCommand->getInterval(),
                'attemptMax' => $newCommand->getAttemptMax(),
                'lastExecutionDate' => null,
                'lastStatus' => null,
                'statusSendEmail' => $newCommand->getStatusSendEmail(),
            ],
        ];
    }
} 