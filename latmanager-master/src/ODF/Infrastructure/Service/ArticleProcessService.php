<?php

namespace App\ODF\Infrastructure\Service;

use App\ODF\Domain\Service\ArticleProcessServiceInterface;
use App\ODF\Domain\Service\LockServiceInterface;

readonly class ArticleProcessService implements ArticleProcessServiceInterface
{
    public function __construct(
        private LockServiceInterface $lockService
    ) {}

    public function processArticlesAndCoupons(array $pieceDetails): array
    {
        $items = [
            'articles' => [],
            'coupons' => []
        ];

        $processedArticles = [];

        foreach ($pieceDetails as $article) {
            if ($article['PLDTYPE'] !== 'C') {
                continue;
            }
            
            $items['articles'][] = $article;
            
            if (isset($processedArticles[$article['ARTID']])) {
                continue;
            }
            $processedArticles[$article['ARTID']] = true;

            $qteTotal = $this->calculateTotalQuantity($pieceDetails, $article['ARTID']);

            $this->lockService->cleanCouponLocks($article['PCDNUM'], $article['ARTCODE']);

            $this->lockService->processLockCoupons($article, $qteTotal, $items);
        }

        return $items;
    }

    /**
     * Calcule la quantité totale pour un article donné
     */
    private function calculateTotalQuantity(array $articles, int $artId): int
    {
        return array_reduce($articles, function($sum, $detail) use ($artId) {
            if ((int)$detail['ARTID'] === $artId && $detail['PLDTYPE'] === 'C') {
                return $sum + (float)$detail['PLDQTE'];
            }
            return $sum;
        }, 0);
    }
}
