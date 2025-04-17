<?php

namespace App\Tests\Unit\ODF\Application\Command\ValidateOrder;

use App\ODF\Application\Command\ValidateOrder\ValidateOrderCommand;
use App\ODF\Application\Command\ValidateOrder\ValidateOrderHandler;
use App\ODF\Application\Command\LockOrder\LockOrderCommand;
use App\ODF\Application\Command\ProcessArticles\ProcessArticlesCommand;
use App\ODF\Application\Query\GetPieceDetails\GetPieceDetailsQuery;
use App\ODF\Application\Query\GetAffaire\GetAffaireQuery;
use App\ODF\Domain\Service\ValidationService;
use App\ODF\Domain\Service\UniqueIdService;
use App\ODF\Domain\ValueObject\ValidationResult;
use App\ODF\Infrastructure\Service\Timer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;

class ValidateOrderHandlerTest extends TestCase
{
    private ValidateOrderHandler $handler;
    private MessageBusInterface $queryBus;
    private MessageBusInterface $commandBus;
    private UniqueIdService $uniqueIdService;
    private ValidationService $validationService;
    private Timer $timer;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->uniqueIdService = $this->createMock(UniqueIdService::class);
        $this->validationService = $this->createMock(ValidationService::class);
        $this->timer = $this->createMock(Timer::class);

        $this->handler = new ValidateOrderHandler(
            $this->queryBus,
            $this->commandBus,
            $this->uniqueIdService,
            $this->validationService,
            $this->timer
        );
    }

    public function testSuccessfulValidation(): void
    {
        $command = new ValidateOrderCommand(123, 'ODF123');

        // Mock UniqueIdService
        $this->uniqueIdService->expects($this->once())
            ->method('checkCloseOdfAndUniqueId')
            ->with(123)
            ->willReturn(['exists' => false]);

        // Mock LockOrder command
        $this->commandBus->expects($this->at(0))
            ->method('dispatch')
            ->with($this->isInstanceOf(LockOrderCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        // Mock GetPieceDetails query
        $pieceDetails = [
            ['PCDNUM' => 'ODF123', 'ARTCODE' => 'ART001', 'PCDQTE' => 5, 'PCDPRIX' => 10]
        ];
        $this->queryBus->expects($this->at(0))
            ->method('dispatch')
            ->with($this->isInstanceOf(GetPieceDetailsQuery::class))
            ->willReturn($pieceDetails);

        // Mock ValidationService
        $this->validationService->expects($this->once())
            ->method('checkArticleDetails')
            ->with($pieceDetails)
            ->willReturn(['status' => 'success', 'messages' => [], 'details' => []]);

        // Mock GetAffaire query
        $affaire = ['AFFNUM' => 'AFF001'];
        $this->queryBus->expects($this->at(1))
            ->method('dispatch')
            ->with($this->isInstanceOf(GetAffaireQuery::class))
            ->willReturn($affaire);

        // Mock ProcessArticles command
        $this->commandBus->expects($this->at(1))
            ->method('dispatch')
            ->with($this->isInstanceOf(ProcessArticlesCommand::class))
            ->willReturn(['processed' => [], 'errors' => []]);

        $result = $this->handler->__invoke($command);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertEquals('success', $result->status);
    }

    public function testExistingOrder(): void
    {
        $command = new ValidateOrderCommand(123, 'ODF123');

        $this->uniqueIdService->expects($this->once())
            ->method('checkCloseOdfAndUniqueId')
            ->with(123)
            ->willReturn([
                'exists' => true,
                'uniqueId' => 'UNIQUE123',
                'memoId' => 456
            ]);

        $result = $this->handler->__invoke($command);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertEquals('existUniqueId', $result->status);
        $this->assertEquals('UNIQUE123', $result->uniqueId);
        $this->assertEquals(456, $result->memoId);
    }

    public function testValidationErrorInArticles(): void
    {
        $command = new ValidateOrderCommand(123, 'ODF123');

        $this->uniqueIdService->expects($this->once())
            ->method('checkCloseOdfAndUniqueId')
            ->with(123)
            ->willReturn(['exists' => false]);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(LockOrderCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $pieceDetails = [
            ['PCDNUM' => 'ODF123', 'ARTCODE' => 'ART001', 'PCDQTE' => 0, 'PCDPRIX' => 10]
        ];
        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(GetPieceDetailsQuery::class))
            ->willReturn($pieceDetails);

        $this->validationService->expects($this->once())
            ->method('checkArticleDetails')
            ->with($pieceDetails)
            ->willReturn([
                'status' => 'error',
                'messages' => [['type' => 'error', 'message' => 'Quantité invalide']]
            ]);

        $result = $this->handler->__invoke($command);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertEquals('error', $result->status);
    }
}
