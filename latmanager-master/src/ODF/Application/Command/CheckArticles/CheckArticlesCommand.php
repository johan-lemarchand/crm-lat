<?php

namespace App\ODF\Application\Command\CheckArticles;

readonly class CheckArticlesCommand
{
    public function __construct(
        private array $pieceDetails
    ) {}

    public function getPieceDetails(): array
    {
        return $this->pieceDetails;
    }
} 