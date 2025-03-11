<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PayunityFlexServiceTest extends KernelTestCase
{
    public function testKernel()
    {
        $this->assertNotNull(self::bootKernel());
    }
}
