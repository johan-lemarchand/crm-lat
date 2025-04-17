<?php

namespace App\ABO\Application\Query\GetPctcode;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetPctcodeHandler
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection,
    ) {}

    public function __invoke(GetPctcodeQuery $query): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
            $result = $qb
                ->select('PCTCODE as code, PCTINTITULE as libelle')
                ->from('PIECE_TRANSFORMATION')
                ->where('PINIDORG = :pinidorg')
                ->orderBy('CASE WHEN PCTCODE = :default_code THEN 0 ELSE 1 END')
                ->addOrderBy('PCTCODE')
                ->setParameter('pinidorg', $query->getPinidorg())
                ->setParameter('default_code', 'ABOCLI->OFFRENOU')
                ->executeQuery()
                ->fetchAllAssociative();

            return $result;
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la récupération des codes de transformation: ' . $e->getMessage());
        }
    }
} 