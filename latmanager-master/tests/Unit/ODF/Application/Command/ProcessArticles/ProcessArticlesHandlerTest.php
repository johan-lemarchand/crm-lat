<?php

namespace App\Tests\Unit\ODF\Application\Command\ProcessArticles;

use App\ODF\Application\Command\ProcessArticles\ProcessArticlesCommand;
use App\ODF\Application\Command\ProcessArticles\ProcessArticlesHandler;
use App\ODF\Domain\Service\ArticleService;
use App\ODF\Infrastructure\Service\Timer;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Query\QueryBuilder;

class ProcessArticlesHandlerTest extends TestCase
{
    private ProcessArticlesHandler $handler;
    private Connection $connection;
    private Timer $timer;
    private ArticleService $articleService;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->timer = $this->createMock(Timer::class);
        $this->articleService = $this->createMock(ArticleService::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);

        $this->handler = new ProcessArticlesHandler(
            $this->connection,
            $this->timer,
            $this->articleService
        );
    }

    public function testSuccessfulArticleProcessing(): void
    {
        $pieceDetails = [
            ['ARTID' => 'ART001', 'PCDNUM' => 'ODF123', 'quantity' => 2]
        ];
        
        $affaire = ['AFFNUM' => 'AFF001'];

        $command = new ProcessArticlesCommand($pieceDetails, $affaire);

        $this->articleService->expects($this->once())
            ->method('processArticles')
            ->willReturn([
                'status' => 'success',
                'articles' => [
                    [
                        'ARTID' => 'ART001',
                        'quantity' => 2,
                        'type' => 'S'
                    ]
                ]
            ]);

        $this->connection->expects($this->any())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->any())
            ->method('select')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->any())
            ->method('from')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->any())
            ->method('where')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->any())
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->any())
            ->method('executeQuery')
            ->willReturn(new class {
                public function fetchOne() {
                    return 10; // Stock disponible
                }
            });

        $result = $this->handler->__invoke($command);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['processedItems']);
        $this->assertEmpty($result['errors']);
    }

    public function testInsufficientStock(): void
    {
        $pieceDetails = [
            ['ARTID' => 'ART001', 'PCDNUM' => 'ODF123', 'quantity' => 5]
        ];
        
        $affaire = ['AFFNUM' => 'AFF001'];

        $command = new ProcessArticlesCommand($pieceDetails, $affaire);

        $this->articleService->expects($this->once())
            ->method('processArticles')
            ->willReturn([
                'status' => 'success',
                'articles' => [
                    [
                        'ARTID' => 'ART001',
                        'quantity' => 5,
                        'type' => 'S'
                    ]
                ]
            ]);

        $this->connection->expects($this->any())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->any())
            ->method('select')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->any())
            ->method('from')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->any())
            ->method('where')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->any())
            ->method('setParameter')
            ->willReturnSelf();
        
        $this->queryBuilder->expects($this->any())
            ->method('executeQuery')
            ->willReturn(new class {
                public function fetchOne() {
                    return 2; // Stock insuffisant
                }
            });

        $result = $this->handler->__invoke($command);

        $this->assertEquals('partial', $result['status']);
        $this->assertEmpty($result['processedItems']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Stock insuffisant', $result['errors'][0]['message']);
    }

    public function testArticleServiceError(): void
    {
        $pieceDetails = [
            ['ARTID' => 'ART001', 'PCDNUM' => 'ODF123']
        ];
        
        $affaire = ['AFFNUM' => 'AFF001'];

        $command = new ProcessArticlesCommand($pieceDetails, $affaire);

        $this->articleService->expects($this->once())
            ->method('processArticles')
            ->willReturn([
                'status' => 'error',
                'message' => 'Erreur de traitement des articles'
            ]);

        $result = $this->handler->__invoke($command);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }
}
