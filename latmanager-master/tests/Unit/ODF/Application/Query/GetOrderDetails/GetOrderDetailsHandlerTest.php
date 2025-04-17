<?php

namespace App\Tests\Unit\ODF\Application\Query\GetOrderDetails;

use App\ODF\Application\Query\GetOrderDetails\GetOrderDetailsQuery;
use App\ODF\Application\Query\GetOrderDetails\GetOrderDetailsHandler;
use App\ODF\Domain\Repository\PieceDetailsRepository;
use App\ODF\Domain\Service\ArticleService;
use PHPUnit\Framework\TestCase;

class GetOrderDetailsHandlerTest extends TestCase
{
    private GetOrderDetailsHandler $handler;
    private PieceDetailsRepository $pieceDetailsRepository;
    private ArticleService $articleService;

    protected function setUp(): void
    {
        $this->pieceDetailsRepository = $this->createMock(PieceDetailsRepository::class);
        $this->articleService = $this->createMock(ArticleService::class);

        $this->handler = new GetOrderDetailsHandler(
            $this->pieceDetailsRepository,
            $this->articleService
        );
    }

    public function testGetOrderDetailsSuccess(): void
    {
        $pcdid = 123;
        $pieceDetails = [
            [
                'PCDNUM' => 'ODF123',
                'ARTID' => 'ART001',
                'PLDTYPE' => 'S',
                'PLDQTE' => 2
            ]
        ];

        $processedArticles = [
            'status' => 'success',
            'articles' => [
                [
                    'ARTID' => 'ART001',
                    'quantity' => 2,
                    'type' => 'S'
                ]
            ]
        ];

        $totals = [
            'totalQuantity' => 2,
            'totalTypes' => ['S' => 1],
            'articleCount' => 1
        ];

        $this->pieceDetailsRepository->expects($this->once())
            ->method('findByPcdid')
            ->with($pcdid)
            ->willReturn($pieceDetails);

        $this->articleService->expects($this->once())
            ->method('processArticles')
            ->with(['pieceDetails' => $pieceDetails])
            ->willReturn($processedArticles);

        $this->articleService->expects($this->once())
            ->method('calculateArticleTotals')
            ->with($processedArticles['articles'])
            ->willReturn($totals);

        $query = new GetOrderDetailsQuery($pcdid);
        $result = $this->handler->__invoke($query);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals($pcdid, $result['order']['pcdid']);
        $this->assertEquals('ODF123', $result['order']['pcdnum']);
        $this->assertEquals($processedArticles['articles'], $result['order']['articles']);
        $this->assertEquals($totals, $result['order']['totals']);
    }

    public function testGetOrderDetailsNotFound(): void
    {
        $pcdid = 123;

        $this->pieceDetailsRepository->expects($this->once())
            ->method('findByPcdid')
            ->with($pcdid)
            ->willReturn([]);

        $query = new GetOrderDetailsQuery($pcdid);
        $result = $this->handler->__invoke($query);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Commande non trouvée', $result['message']);
    }

    public function testGetOrderDetailsWithArticleProcessingError(): void
    {
        $pcdid = 123;
        $pieceDetails = [
            [
                'PCDNUM' => 'ODF123',
                'ARTID' => 'ART001',
                'PLDTYPE' => 'S'
            ]
        ];

        $this->pieceDetailsRepository->expects($this->once())
            ->method('findByPcdid')
            ->with($pcdid)
            ->willReturn($pieceDetails);

        $this->articleService->expects($this->once())
            ->method('processArticles')
            ->willReturn([
                'status' => 'error',
                'message' => 'Erreur de traitement des articles'
            ]);

        $query = new GetOrderDetailsQuery($pcdid);
        $result = $this->handler->__invoke($query);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }
}
