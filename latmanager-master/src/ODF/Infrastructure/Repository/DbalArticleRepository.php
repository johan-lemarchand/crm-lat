<?php

namespace App\ODF\Infrastructure\Repository;

use App\ODF\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class DbalArticleRepository implements ArticleRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection
    ) {}

    public function findArticlesByPcdid(int $pcdid): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
            
            return $qb
                ->select('pd.PLDID', 'a.ARTCODE', 'a.ARTDESIGNATION', 'pd.PLDQTE', 'pd.COUPON', 'pd.PLDTYPE')
                ->from('PIECELIGNEDETAIL', 'pd')
                ->innerJoin('pd', 'ARTICLES', 'a', 'a.ARTID = pd.ARTID')
                ->where('pd.PCDID = :pcdid')
                ->setParameter('pcdid', $pcdid)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la récupération des articles: ' . $e->getMessage());
        }
    }

    public function findArticlesWithTypes(int $pcdid): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();
            
            return $qb
                ->select('pd.PLDTYPE', 'a.ARTCODE', 'pd.PLDQTE')
                ->from('PIECELIGNEDETAIL', 'pd')
                ->innerJoin('pd', 'ARTICLES', 'a', 'a.ARTID = pd.ARTID')
                ->where('pd.PCDID = :pcdid')
                ->setParameter('pcdid', $pcdid)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            throw new \RuntimeException('Erreur lors de la récupération des types d\'articles: ' . $e->getMessage());
        }
    }
} 