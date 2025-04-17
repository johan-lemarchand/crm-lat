<?php

namespace App\ODF\Application\Command\UpdateMemoAndApi;

readonly class UpdateMemoAndApiCommand
{
    public function __construct(
        private int $pcdid,
        private int $memoId,
        private string $user,
        private array $messages,
        private array $apiData
    ) {}

    public function getPcdid(): int
    {
        return $this->pcdid;
    }

    public function getMemoId(): int
    {
        return $this->memoId;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getApiData(): array
    {
        return $this->apiData;
    }
} 