<?php

namespace App\ODF\Application\Command\ProcessArticles;

readonly class ProcessArticlesCommand
{
    public function __construct(
        private array $pieceDetails
    ) {}

    public function getPiecesDetails(): array
    {
        return $this->pieceDetails;
    }
}
