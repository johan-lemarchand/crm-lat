<?php

namespace App\ODF\Domain\Service;

use Exception;

interface TrimbleServiceInterface
{
    /**
     * Crée une commande Trimble
     */
    public function createOrder(array $orderDetails): array;

    /**
     * Récupère une activation
     */
    public function getActivation(string $numOrder): array;

    /**
     * Récupère une activation par numéro de série
     */
    public function getActivationBySerial(string $serialNumber): array;

    /**
     * Récupère une commande par numéro de série
     */
    public function getOrderBySerialNumber(string $serialNumber): array;

    /**
     * Récupère une commande par identifiant unique
     *
     * @throws Exception
     */
    public function getOrderByUniqueId(string $uniqueId): array;

    public function checkSerialNumber(string $serialNumber): array;
}
