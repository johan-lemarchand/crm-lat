<?php

namespace App\ODF\Application\Command\CloseOdf;

readonly class CloseOdfCommand
{
    public function __construct(
        private string $pcdnum,
        private int    $memoId,
        private int    $pcdid,
        private string $orderNumber,
        private string $user
    ) {
    }

    public function getPcdnum(): string
    {
        return $this->pcdnum;
    }

    public function getMemoId(): int
    {
        return $this->memoId;
    }

    public function getPcdid(): int
    {
        return $this->pcdid;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getUser(): string
    {
        return $this->user;
    }
}
