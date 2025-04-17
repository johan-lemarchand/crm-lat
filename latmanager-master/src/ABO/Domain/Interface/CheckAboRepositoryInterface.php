<?php

namespace App\ABO\Domain\Interface;

interface CheckAboRepositoryInterface
{
    public function findByPcvnum(string $pcvnum): array;
    public function findAdrNewCodeFinalClient(string $newCodeClient): array;
    public function findAdrNewCodeClientInvoice(string $newCodeClient): array;
    public function findAdrNewCodeClientDelivery(string $newCodeClient): array;
}
