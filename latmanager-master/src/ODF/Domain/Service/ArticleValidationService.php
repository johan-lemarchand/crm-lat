<?php

namespace App\ODF\Domain\Service;

class ArticleValidationService
{
    private const AUTHORIZED_ARTICLES = [
        '104476-10',
        '88455-10',
        '104476-30'
    ];

    private const ARTICLE_COUPON_MAPPING = [
        '88455-10' => '88455-10-C',
        '104476-10' => '120373-10',
        '104476-30' => '1133378-10-CPN'
    ];

    public function isArticleAuthorized(string $articleCode): bool
    {
        return in_array($articleCode, self::AUTHORIZED_ARTICLES);
    }

    public function getCouponForArticle(string $articleCode): ?string
    {
        return self::ARTICLE_COUPON_MAPPING[$articleCode] ?? null;
    }

    public function getArticleForCoupon(string $couponCode): ?string
    {
        return array_search($couponCode, self::ARTICLE_COUPON_MAPPING) ?: null;
    }
} 