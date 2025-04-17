<?php

namespace App\ODF\Infrastructure\Service;

use App\ODF\Domain\Service\LockServiceInterface;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\ODF\Domain\Repository\CouponRepositoryInterface;

readonly class LockService implements LockServiceInterface
{
    private string $lockFilePath;

    private const MAX_ATTEMPTS = 10;

    public function __construct(
        private CouponRepositoryInterface $couponRepository,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir
    ) {
        $this->lockFilePath = $projectDir . '/var/locked_coupons.json';
        $this->initLockDirectory();
    }

    private function initLockDirectory(): void
    {
        $dir = dirname($this->lockFilePath);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public function cleanExpiredLocks(): void
    {
        if (file_exists($this->lockFilePath)) {
            $lockedCoupons = json_decode(file_get_contents($this->lockFilePath), true) ?? [];
            $now = time();
            $modified = false;
            
            foreach ($lockedCoupons as $serial => $lock) {
                if ($now - $lock['timestamp'] >= 600) {
                    unset($lockedCoupons[$serial]);
                    $modified = true;
                }
            }
            
            if ($modified) {
                file_put_contents($this->lockFilePath, json_encode($lockedCoupons), LOCK_EX);
            }
        }
    }

    public function isSerialNumberLocked(string $serialNumber): bool
    {
        $this->cleanExpiredLocks();
        
        if (file_exists($this->lockFilePath)) {
            $lockedCoupons = json_decode(file_get_contents($this->lockFilePath), true) ?? [];
            return isset($lockedCoupons[$serialNumber]);
        }
        return false;
    }

    public function lockSerialNumbers(array $serialNumbers, string $odf, string $articleCode, string $proCodeParent): void
    {
        $this->cleanExpiredLocks();
        $lockedCoupons = [];
        
        if (file_exists($this->lockFilePath)) {
            $lockedCoupons = json_decode(file_get_contents($this->lockFilePath), true) ?? [];
        }

        foreach ($serialNumbers as $serial) {
            $lockedCoupons[$serial] = [
                'timestamp' => time(),
                'odf' => $odf,
                'artCode' => $articleCode,
                'proCodeParent' => $proCodeParent
            ];
        }
        
        file_put_contents($this->lockFilePath, json_encode($lockedCoupons), LOCK_EX);
    }

    public function unlockSerialNumbers(array $coupons): void
    {
        if (file_exists($this->lockFilePath)) {
            $lockedCoupons = json_decode(file_get_contents($this->lockFilePath), true) ?? [];
            foreach ($coupons as $coupon) {
                unset($lockedCoupons[$coupon]);
            }
            
            file_put_contents($this->lockFilePath, json_encode($lockedCoupons), LOCK_EX);
        }
    }

    public function cleanCouponLocks(string $odf, string $artCode): void
    {
        if (file_exists($this->lockFilePath)) {
            $lockedCoupons = json_decode(file_get_contents($this->lockFilePath), true) ?? [];
            
            $updatedCoupons = array_filter($lockedCoupons, function($couponData) use ($odf, $artCode) {
                return $couponData['odf'] !== $odf || $couponData['artCode'] !== $artCode;
            });
            
            if (count($updatedCoupons) !== count($lockedCoupons)) {
                file_put_contents($this->lockFilePath, json_encode($updatedCoupons, JSON_PRETTY_PRINT));
            }
        }
    }

    /**
     * @throws Exception
     */
    public function processLockCoupons(array $pieceDetail, int $qteTotal, array &$items): void
    {
        $coupons = [];
        $attempts = 0;
        $odf = $pieceDetail['PCDNUM'];
        $serialNumbers = [];
        $proCodeParent = $pieceDetail['PROCODE_PARENT'] ?? $pieceDetail['PROCODE'];

        try {
            while (count($coupons) < $qteTotal && $attempts < self::MAX_ATTEMPTS) {
                $attempts++;
                $remaining = $qteTotal - count($coupons);
                $tempCoupons = $this->getCoupon($pieceDetail['ARTID'], $remaining);

                if (empty($tempCoupons)) {
                    throw new Exception("Plus de coupons disponibles en base de données");
                }

                foreach ($tempCoupons as $coupon) {
                    if (!$this->isSerialNumberLocked($coupon['OPENUMSERIE'])) {
                        $coupons[] = $coupon;
                    }
                }
            }
            
            if (count($coupons) < $qteTotal) {
                throw new Exception("Impossible de trouver suffisamment de coupons non verrouillés après {$attempts} tentatives");
            }

            $serialNumbers = array_column($coupons, 'OPENUMSERIE');
            $this->lockSerialNumbers($serialNumbers, $odf, $pieceDetail['ARTCODE'], $proCodeParent);
            
            foreach ($coupons as $coupon) {
                $coupon['ARTCODE'] = $pieceDetail['ARTCODE'];
                $items['coupons'][] = $coupon;
            }

        } catch (Exception $e) {
            if (!empty($serialNumbers)) {
                $this->unlockSerialNumbers($serialNumbers);
            }
            throw $e;
        }
    }

    private function getCoupon(int $artId, int $quantity): array
    {
        $lockedCoupons = [];
        if (file_exists($this->lockFilePath)) {
            $lockedCoupons = json_decode(file_get_contents($this->lockFilePath), true) ?? [];
        }
        $lockedSerials = array_keys($lockedCoupons);

        return $this->couponRepository->findAvailableCoupons($artId, $quantity, $lockedSerials);
    }

    public function checkLock(int $pcdid, string $pcdnum): array
    {
        if (file_exists($this->lockFilePath)) {
            $lockedCoupons = json_decode(file_get_contents($this->lockFilePath), true) ?? [];
            
            foreach ($lockedCoupons as $couponData) {
                if ($couponData['odf'] === $pcdnum) {
                    return [
                        'status' => 'error',
                        'messages' => [[
                            'message' => 'La pièce est déjà en cours de traitement',
                            'status' => 'error'
                        ]]
                    ];
                }
            }
        }

        return [
            'status' => 'success',
            'messages' => []
        ];
    }
}
