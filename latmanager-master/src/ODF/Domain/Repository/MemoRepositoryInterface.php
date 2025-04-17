<?php

namespace App\ODF\Domain\Repository;

interface MemoRepositoryInterface
{
    public function findMemoById(int $memoId): ?string;
    public function updateMemo(int $memoId, string $memo): void;
    public function getMemoText(int $memoId): ?string;
} 