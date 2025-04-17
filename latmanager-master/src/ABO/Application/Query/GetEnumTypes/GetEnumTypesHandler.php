<?php

namespace App\ABO\Application\Query\GetEnumTypes;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetEnumTypesHandler
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection,
    ) {}

    public function __invoke(GetEnumTypesQuery $query): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
            $result = $qb
                ->select('DISTINCT ENULIBELLE as libelle')
                ->from('ENUMERES')
                ->where('ENUTYPE = :type')
                ->orderBy('ENULIBELLE')
                ->setParameter('type', $query->type)
                ->executeQuery()
                ->fetchAllAssociative();

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des énumérations: ' . $e->getMessage()
            ];
        }
    }
} 