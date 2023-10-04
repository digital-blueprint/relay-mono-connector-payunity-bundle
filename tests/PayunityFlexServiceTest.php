<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Tests;

use Dbp\Relay\MonoBundle\PaymentServiceProvider\PaymentServiceProviderServiceInterface;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\PayunityFlexService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PayunityFlexServiceTest extends KernelTestCase
{
    public function testService()
    {
        self::bootKernel();
        $container = static::getContainer();

        // The service ID is public API, so make sure it doesn't change
        $service = $container->get('Dbp\Relay\MonoConnectorPayunityBundle\Service\PayunityFlexService');
        $this->assertTrue($service instanceof PayunityFlexService);
        $this->assertTrue($service instanceof PaymentServiceProviderServiceInterface);
    }
}
