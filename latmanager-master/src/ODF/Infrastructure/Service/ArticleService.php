<?php

namespace App\ODF\Infrastructure\Service;

use App\ODF\Domain\Service\ArticleServiceInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ArticleService implements ArticleServiceInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.wavesoft_connection')]
        private Connection $connection,
        private Timer $timer
    ) {}

    public function processArticles(array $data): array
    {
        $this->timer->logStep(
            "Traitement des articles",
            'info',
            'pending',
            "Début du traitement des articles"
        );

        try {
            if (empty($data['pieceDetails'])) {
                throw new \InvalidArgumentException("Aucun détail de pièce fourni");
            }

            $qb = $this->connection->createQueryBuilder();
            $articles = $qb
                ->select('a.*')
                ->from('ARTICLES', 'a')
                ->join('a', 'PIECES_DETAILS', 'pd', 'pd.ARTID = a.ARTID')
                ->where('pd.PCDID = :pcdid')
                ->setParameter('pcdid', $data['pieceDetails']['PCDID'])
                ->executeQuery()
                ->fetchAllAssociative();

            if (empty($articles)) {
                $this->timer->logStep(
                    "Traitement des articles",
                    'warning',
                    'done',
                    "Aucun article trouvé pour cette pièce"
                );

                return [
                    'status' => 'warning',
                    'message' => 'Aucun article trouvé pour cette pièce',
                    'articles' => []
                ];
            }

            // Enrichir les articles avec les quantités
            foreach ($articles as &$article) {
                $article['quantity'] = $data['pieceDetails']['PCDQTE'] ?? 1;
            }

            $this->timer->logStep(
                "Traitement des articles",
                'info',
                'done',
                sprintf("Traitement terminé : %d articles trouvés", count($articles))
            );

            return [
                'status' => 'success',
                'articles' => $articles
            ];

        } catch (\Exception $e) {
            $this->timer->logStep(
                "Traitement des articles",
                'error',
                'error',
                "Erreur lors du traitement des articles : " . $e->getMessage()
            );

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
