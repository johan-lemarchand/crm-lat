<?php

namespace App\Tests\Unit\ODF\Application\Query\GetOrderDetails;

use App\ODF\Application\Query\GetOrderDetails\GetOrderDetailsQuery;
use PHPUnit\Framework\TestCase;

class GetOrderDetailsQueryTest extends TestCase
{
    public function testGetPcdid(): void
    {
        $pcdid = 123;
        $query = new GetOrderDetailsQuery($pcdid);

        $this->assertEquals($pcdid, $query->getPcdid());
    }
}
