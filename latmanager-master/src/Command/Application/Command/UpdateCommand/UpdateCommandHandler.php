<?php

namespace App\Command\Application\Command\UpdateCommand;

use App\Command\Domain\Repository\CommandRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UpdateCommandHandler
{
    public function __construct(
        private CommandRepositoryInterface $commandRepository
    ) {}

    public function __invoke(UpdateCommandCommand $command): array
    {
        $existingCommand = $this->commandRepository->find($command->getId());
        
        if (!$existingCommand) {
            throw new \RuntimeException('Command not found');
        }

        $existingCommand->setName($command->getName());
        $existingCommand->setScriptName($command->getScriptName());
        $existingCommand->setStartTime($command->getStartTime() ? new \DateTimeImmutable($command->getStartTime()) : null);
        $existingCommand->setEndTime($command->getEndTime() ? new \DateTimeImmutable($command->getEndTime()) : null);
        $existingCommand->setRecurrence($command->getRecurrence());
        $existingCommand->setActive($command->isActive());

        if ($command->getInterval() !== null) {
            $existingCommand->setInterval($command->getInterval());
        }
        if ($command->getAttemptMax() !== null) {
            $existingCommand->setAttemptMax($command->getAttemptMax());
        }
        if ($command->getStatusSendEmail() !== null) {
            $existingCommand->setStatusSendEmail($command->getStatusSendEmail());
        }

        $this->commandRepository->save($existingCommand);

        return [
            'id' => $existingCommand->getId(),
            'name' => $existingCommand->getName(),
            'scriptName' => $existingCommand->getScriptName(),
            'recurrence' => $existingCommand->getRecurrence(),
            'interval' => $existingCommand->getInterval(),
            'attemptMax' => $existingCommand->getAttemptMax(),
            'lastExecutionDate' => $existingCommand->getLastExecutionDate()?->format('Y-m-d H:i:s'),
            'lastStatus' => $existingCommand->getLastStatus(),
            'startTime' => $existingCommand->getStartTime()?->format('H:i'),
            'endTime' => $existingCommand->getEndTime()?->format('H:i'),
            'active' => $existingCommand->isActive(),
            'statusSendEmail' => $existingCommand->getStatusSendEmail(),
        ];
    }
} 