<?php

namespace App\Applications\Wavesoft\scripts\Deposit\Service;

use App\Applications\Wavesoft\WavesoftClient;
use App\ODF\Infrastructure\Service\AutomateService;
use Psr\Log\LoggerInterface;
use App\Entity\CommandExecution;

class DelockCouponService
{

    private ?CommandExecution $execution = null;

    public function __construct(
        private readonly WavesoftClient $wavesoftClient,
        private readonly LoggerInterface $logger,
        private readonly AutomateService $automateService
    ) {
    }

    public function setExecution(CommandExecution $execution): void
    {
        $this->execution = $execution;
    }

    public function DelockCoupon(): array
    {
        if (!$this->execution) {
            throw new \RuntimeException('CommandExecution must be set before running delock-coupon');
        }
        $query = "SELECT PCD.PCDNUM 
                FROM PIECEDIVERS PCD
                WHERE PCDNUM like 'BTRODF%' and PCDISSOLDE= 'N'
                AND 'BDF'+right(PCDNUM,10) not in (SELECT PCDNUM FROM PIECEDIVERS WHERE PCDNUM like 'BDFODF%')
                AND PCDDATEEFFET < CAST(GETDATE() AS DATE)";

        $stmt = $this->wavesoftClient->getConnection()->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $processed = [];
        foreach ($result as $item) {
            $pcdnum = $item['PCDNUM'];
            try {
                $this->logger->info('Tentative de suppression du BTR', [
                    'pcdnum' => $pcdnum
                ]);
                
                $success = $this->automateService->processDeleteAutomate($pcdnum);
                
                if ($success) {
                    $processed[] = [
                        'pcdnum' => $pcdnum,
                        'status' => 'success'
                    ];
                    $this->logger->info(sprintf('Successfully processed delock coupon for %s', $pcdnum));
                } else {
                    $processed[] = [
                        'pcdnum' => $pcdnum,
                        'status' => 'error',
                        'message' => 'Échec de la suppression'
                    ];
                    $this->logger->error('Échec de la suppression');
                }
            } catch (\Exception $e) {
                $processed[] = [
                    'pcdnum' => $pcdnum,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $this->logger->error('Échec de la suppression', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'total' => count($result),
            'processed' => $processed
        ];
    }
} 