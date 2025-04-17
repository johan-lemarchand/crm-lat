<?php

namespace App\Utils;

/**
 * Constantes pour les types de synchronisation
 */
class SyncConstants
{
    // Praxedo
    public const CLIENTS_PRAXEDO = 'CLIENTS_PRAXEDO';
    public const INTERVENTIONS_PRAXEDO = 'INTERVENTIONS_PRAXEDO';
    public const TIMESLOTS_PRAXEDO = 'TIMESLOTS_PRAXEDO';
    public const ACTIVITIES_PRAXEDO = 'ACTIVITIES_PRAXEDO';
    public const ARTICLES_PRAXEDO = 'ARTICLES_PRAXEDO';
    
    // SAV
    public const SAV_SUPPORT_REAL = 'SAV_SUPPORT_REAL';
    public const SAV_TERRAIN_VALID = 'SAV_TERRAIN_VALID';
    public const SAV_TERRAIN_REAL = 'SAV_TERRAIN_REAL';
    public const SAV_REPAIR_VALID = 'SAV_REPAIR_VALID';
    public const SAV_REPAIR_REAL = 'SAV_REPAIR_REAL';
    public const NEW_SAV_REPAIR = 'NEW_SAV_REPAIR';
    public const SAV_SUPPORT_VALID = 'SAV_SUPPORT_VALID';
    
    // Interventions
    public const INTER_MONT_REAL = 'INTER_MONT_REAL';
    public const INTER_MONT_VALID = 'INTER_MONT_VALID';
    public const DISPARITION_INTER = 'DISPARITION_INTER';
    
    // Ventes et PV
    public const CHECK_VENTES_VALID = 'CHECK_VENTES_VALID';
    public const UPDATE_PV = 'UPDATE_PV';
    
    // Clients
    public const UPDATE_CLIENTS = 'UPDATE_CLIENTS';
    
    /**
     * Obtient tous les types de synchronisation
     * 
     * @return array Tous les types de synchronisation
     */
    public static function getAllTypes(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return $reflection->getConstants();
    }
    
    /**
     * Obtient les types de synchronisation par catégorie
     * 
     * @return array Types de synchronisation par catégorie
     */
    public static function getTypesByCategory(): array
    {
        return [
            'Praxedo' => [
                self::CLIENTS_PRAXEDO,
                self::INTERVENTIONS_PRAXEDO,
                self::TIMESLOTS_PRAXEDO,
                self::ACTIVITIES_PRAXEDO,
                self::ARTICLES_PRAXEDO,
            ],
            'SAV' => [
                self::SAV_SUPPORT_REAL,
                self::SAV_TERRAIN_VALID,
                self::SAV_TERRAIN_REAL,
                self::SAV_REPAIR_VALID,
                self::SAV_REPAIR_REAL,
                self::NEW_SAV_REPAIR,
                self::SAV_SUPPORT_VALID,
            ],
            'Interventions' => [
                self::INTER_MONT_REAL,
                self::INTER_MONT_VALID,
                self::DISPARITION_INTER,
            ],
            'Ventes et PV' => [
                self::CHECK_VENTES_VALID,
                self::UPDATE_PV,
            ],
            'Clients' => [
                self::UPDATE_CLIENTS,
            ],
        ];
    }
} 