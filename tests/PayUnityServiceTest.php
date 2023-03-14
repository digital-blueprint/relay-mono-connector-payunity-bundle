<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Tests;

use Dbp\Relay\MonoConnectorPayunityBundle\Service\Utils;
use PHPUnit\Framework\TestCase;

class PayUnityServiceTest extends TestCase
{
    public function testExtendReturnUrl()
    {
        $this->assertSame('http://localhost/payunity', Utils::extendReturnUrl('http://localhost'));
        $this->assertSame('http://localhost/payunity', Utils::extendReturnUrl('http://localhost/'));
    }

    public function testExtractCheckoutIdFromPspData()
    {
        $url = 'payunity?id=9F4895B46102301B0A8C18616D4611BB.uat01-vm-tx02&resourcePath=/v1/checkouts/9F4895B46102301B0A8C18616D4611BB.uat01-vm-tx02/payment';
        $this->assertSame('9F4895B46102301B0A8C18616D4611BB.uat01-vm-tx02', Utils::extractCheckoutIdFromPspData($url));
        $url = '/payunity?id=9F4895B46102301B0A8C18616D4611BB.uat01-vm-tx02&resourcePath=/v1/checkouts/9F4895B46102301B0A8C18616D4611BB.uat01-vm-tx02/payment';
        $this->assertSame('9F4895B46102301B0A8C18616D4611BB.uat01-vm-tx02', Utils::extractCheckoutIdFromPspData($url));
        $this->assertSame('foo bar', Utils::extractCheckoutIdFromPspData('payunity?resourcePath=/v1/checkouts/foo%20bar/payment'));
        $this->assertFalse(Utils::extractCheckoutIdFromPspData('/payunity'));
        $this->assertFalse(Utils::extractCheckoutIdFromPspData('payunity?resourcePath=nope'));
    }

    public function testIsPayunityPspData()
    {
        $this->assertTrue(Utils::isPayunityPspData('/payunity'));
        $this->assertTrue(Utils::isPayunityPspData('payunity'));
        $this->assertTrue(Utils::isPayunityPspData('payunity?ok'));
        $this->assertFalse(Utils::isPayunityPspData('payunity/nope'));
        $this->assertFalse(Utils::isPayunityPspData('/nope'));
    }
}
