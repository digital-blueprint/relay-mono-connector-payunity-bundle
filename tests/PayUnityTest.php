<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Tests;

use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\ApiException;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Connection;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PaymentType;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PayUnityApi;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Tools;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class PayUnityTest extends TestCase
{
    /**
     * @var Connection
     */
    private $conn;

    private $api;

    public function testFloatToAmount()
    {
        $this->assertSame('1.50', Tools::floatToAmount(1.50));
        $this->assertSame('1.15', Tools::floatToAmount(1.15));
        $this->assertSame('5000.00', Tools::floatToAmount(5000));
        $this->assertSame('999999.00', Tools::floatToAmount(999999));
        $this->assertSame('-42.42', Tools::floatToAmount(-42.42));
    }

    protected function setUp(): void
    {
        $this->conn = new Connection('http://localhost', 'nope', 'nope');
        $this->mockResponses([]);
        $this->api = new PayUnityApi($this->conn);
    }

    private function mockResponses(array $responses)
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $this->conn->setClientHandler($stack);
    }

    public function testPrepareCheckout()
    {
        $BODY = '{
  "result":{
    "code":"000.200.100",
    "description":"successfully created checkout"
  },
  "buildNumber":"9a7f25e2679d81630133ac842b2597f4a8f41935@2022-08-25 09:15:46 +0000",
  "timestamp":"2022-08-25 11:20:49+0000",
  "ndc":"A03A5F2526733FBB37C419B815E1C091.uat01-vm-tx03",
  "id":"A03A5F2526733FBB37C419B815E1C091.uat01-vm-tx03"
}';
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], $BODY),
        ]);

        $data = $this->api->prepareCheckout('42.42', 'EUR', PaymentType::DB);

        $this->assertSame('000.200.100', $data->getCode());
        $this->assertSame('successfully created checkout', $data->getDescription());
        $this->assertSame('A03A5F2526733FBB37C419B815E1C091.uat01-vm-tx03', $data->getId());
    }

    public function testPrepareCheckoutNegative()
    {
        $BODY = '{"result":{"code":"200.300.404","description":"invalid or missing parameter","parameterErrors":[{"name":"amount","value":"-20.20","message":"must match ^[0-9]{1,12}(\\\\.[0-9]{2})?$"}]},"buildNumber":"9a7f25e2679d81630133ac842b2597f4a8f41935@2022-08-25 09:15:46 +0000","timestamp":"2022-08-25 12:02:36+0000","ndc":"3AF4CAAE15AAE665A7CD2838392C61B3.uat01-vm-tx03"}';
        $this->mockResponses([
            new Response(400, ['Content-Type' => 'application/json'], $BODY),
        ]);
        $this->expectException(ApiException::class);
        $this->expectErrorMessage('[200.300.404] invalid or missing parameter');
        $this->api->prepareCheckout('-20.20', 'EUR', PaymentType::DB);
    }
}
