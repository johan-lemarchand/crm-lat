<?php

namespace App\ABO\Application\Command\CreateAbo;

use App\ODF\Infrastructure\Service\AutomateService;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CreateAboCommandHandler
{
    public function __construct(
        private AutomateService $automateService,
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(CreateAboCommand $command): array
    {
        return $this->automateService->createAbo($command->automateE,$command->automateAA,$command->automateAB, $command->automateAE,$command->automateAF,$command->automateAL, $command->lignes, $command->memoId, $command->getUser(), $command->getPcvnum());
    }
} 