<?php

namespace App\ODF\Domain\Service;

interface TcpServiceInterface
{
    /**
     * @throws \Exception
     */
    public function sendWaveSoftCommand(string $data): string|false;
}
