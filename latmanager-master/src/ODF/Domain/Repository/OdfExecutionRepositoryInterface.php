<?php

namespace App\ODF\Domain\Repository;

use App\Entity\OdfExecution;
use App\Entity\OdfLog;

interface OdfExecutionRepositoryInterface
{
    public function save(OdfExecution $execution, bool $flush = false): void;
    
    public function findLastExecutionForLog(OdfLog $odfLog): ?OdfExecution;
} 