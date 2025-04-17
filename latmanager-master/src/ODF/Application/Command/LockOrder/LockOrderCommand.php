<?php

namespace App\ODF\Application\Command\LockOrder;

class LockOrderCommand
{
    public function __construct(
        public readonly int $pcdid,
        public readonly string $pcdnum
    ) {}
}
