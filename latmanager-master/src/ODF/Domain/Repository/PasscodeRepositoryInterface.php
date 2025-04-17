<?php

namespace App\ODF\Domain\Repository;

interface PasscodeRepositoryInterface
{
    /**
     * Récupère les passcodes d'activation pour une commande donnée
     *
     * @param string $orderNumber Numéro de commande
     * @return array Liste des passcodes et informations d'activation
     */
    public function getPasscodes(string $orderNumber): array;
} 