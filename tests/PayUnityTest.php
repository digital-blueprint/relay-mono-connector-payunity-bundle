<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Tests;

use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\ApiException;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Connection;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PaymentType;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PayUnityApi;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\ResultCode;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Tools;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
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

    private function mockResponses(array $responses, array &$container = [])
    {
        $history = Middleware::history($container);
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push($history);
        $this->conn->setClientHandler($stack);
    }

    public function testResultCode()
    {
        $this->assertTrue((new ResultCode('000.000.000'))->isSuccessfullyProcessed());
        $this->assertFalse((new ResultCode('000.000.000'))->isSuccessfullyProcessedNeedsManualReview());

        $this->assertTrue((new ResultCode('000.400.000'))->isSuccessfullyProcessedNeedsManualReview());
        $this->assertFalse((new ResultCode('000.400.000'))->isSuccessfullyProcessed());

        $this->assertTrue((new ResultCode('000.200.000'))->isPending());
        $this->assertFalse((new ResultCode('000.200.000'))->isPendingExtra());

        $this->assertTrue((new ResultCode('100.400.500'))->isPendingExtra());
        $this->assertFalse((new ResultCode('100.400.500'))->isPending());
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
        $history = [];
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], $BODY),
        ], $history);

        $data = $this->api->prepareCheckout('42.42', 'EUR', PaymentType::DEBIT);
        $this->assertSame('/v1/checkouts', $history[0]['request']->getRequestTarget());
        $this->assertSame('000.200.100', $data->getResult()->getCode());
        $this->assertSame('successfully created checkout', $data->getResult()->getDescription());
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
        $this->api->prepareCheckout('-20.20', 'EUR', PaymentType::DEBIT);
    }

    public function testGetPaymentScriptSrc()
    {
        $this->assertSame(
            'http://localhost/v1/paymentWidgets.js?checkoutId=nop%26e',
            $this->api->getPaymentScriptSrc('nop&e'));
    }

    public function testGetPaymentStatusAnd()
    {
        $BODY = '{
  "id":"8ac7a4a282d522f70182d53e7a873640",
  "paymentType":"DB",
  "paymentBrand":"SOFORTUEBERWEISUNG",
  "amount":"92.00",
  "currency":"EUR",
  "descriptor":"0337.3452.9321 Sofort_Channel",
  "result":{
    "code":"000.100.110",
    "description":"Request successfully processed in \'Merchant in Integrator Test Mode\'"
  },
  "resultDetails":{
    "ConnectorTxID1":"8ac7a4a282d522f70182d53e7a873640",
    "clearingInstituteName":"SOFORT-Banking_Test"
  },
  "bankAccount":{
    "holder":"Test Holder",
    "bankName":"Test Bank",
    "number":"121342",
    "iban":"DE23100000001234567890",
    "bankCode":"TestBank",
    "bic":"MARKDEF1100",
    "country":"DE"
  },
  "customer":{
    "ip":"127.12.123.12",
    "ipCountry":"AT",
    "browserFingerprint":{
      "value":"dHVncmF6LWR1bW15LWZvci11bml0dGVzdHM="
    }
  },
  "buildNumber":"9a7f25e2679d81630133ac842b2597f4a8f41935@2022-08-25 09:15:46 +0000",
  "timestamp":"2022-08-25 13:43:44.065+0000",
  "ndc":"9929656AD0B361BBC3AF31B3ECDCE28B.uat01-vm-tx04"
}';
        $history = [];
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], $BODY),
        ], $history);

        $paymentStatus = $this->api->getPaymentStatus('9929656AD0B361BBC3AF31B3ECDCE28B');
        $this->assertSame('/v1/checkouts/9929656AD0B361BBC3AF31B3ECDCE28B/payment?entityId=nope', $history[0]['request']->getRequestTarget());
        $this->assertTrue($paymentStatus->getResult()->isSuccessfullyProcessed());
        $this->assertSame($paymentStatus->getId(), '8ac7a4a282d522f70182d53e7a873640');
    }

    public function testGetPaymentStatusAfterSuccess()
    {
        $BODY = '{
  "result":{
    "code":"200.300.404",
    "description":"invalid or missing parameter - (opp) No payment session found for the requested id - are you mixing test/live servers or have you paid more than 30min ago?"
  },
  "buildNumber":"9a7f25e2679d81630133ac842b2597f4a8f41935@2022-08-25 09:15:46 +0000",
  "timestamp":"2022-08-25 13:51:06+0000",
  "ndc":"8a8294174b7ecb28014b9699220015ca_2bac8e434db544f7898023457f80c78f"
}';
        $this->mockResponses([
            new Response(400, ['Content-Type' => 'application/json'], $BODY),
        ]);

        $this->expectException(ApiException::class);
        $this->expectErrorMessage('[200.300.404] invalid or missing parameter - (opp) No payment session found for the requested id - are you mixing test/live servers or have you paid more than 30min ago?');
        $this->api->getPaymentStatus('9929656AD0B361BBC3AF31B3ECDCE28B');
    }

    public function testQueryPayment()
    {
        $BODY = '{
  "id":"8ac7a4a282d522f70182d53e7a873640",
  "paymentType":"DB",
  "paymentBrand":"SOFORTUEBERWEISUNG",
  "amount":"92.00",
  "currency":"EUR",
  "descriptor":"0337.3452.9321 Sofort_Channel",
  "result":{
    "code":"000.100.110",
    "description":"Request successfully processed in \'Merchant in Integrator Test Mode\'"
  },
  "resultDetails":{
    "ConnectorTxID1":"8ac7a4a282d522f70182d53e7a873640",
    "clearingInstituteName":"SOFORT-Banking_Test"
  },
  "bankAccount":{
    "holder":"Test Holder",
    "bankName":"Test Bank",
    "number":"121342",
    "iban":"DE23100000001234567890",
    "bankCode":"TestBank",
    "bic":"MARKDEF1100",
    "country":"DE"
  },
  "customer":{
    "ip":"127.12.123.12",
    "ipCountry":"AT",
    "browserFingerprint":{
      "value":"dHVncmF6LWR1bW15LWZvci11bml0dGVzdHM="
    }
  },
  "buildNumber":"9a7f25e2679d81630133ac842b2597f4a8f41935@2022-08-25 09:15:46 +0000",
  "timestamp":"2022-08-25 13:43:44.065+0000",
  "ndc":"9929656AD0B361BBC3AF31B3ECDCE28B.uat01-vm-tx04"
}';
        $history = [];
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], $BODY),
        ], $history);

        $paymentStatus = $this->api->queryPayment('8ac7a4a282d522f70182d53e7a873640');
        $this->assertSame('/v1/query/8ac7a4a282d522f70182d53e7a873640?entityId=nope', $history[0]['request']->getRequestTarget());
        $this->assertTrue($paymentStatus->getResult()->isSuccessfullyProcessed());
        $this->assertSame($paymentStatus->getId(), '8ac7a4a282d522f70182d53e7a873640');
    }

    public function testQueryPaymentUnknown()
    {
        $BODY = '{
  "result":{
    "code":"700.400.580",
    "description":"cannot find transaction"
  },
  "buildNumber":"9a7f25e2679d81630133ac842b2597f4a8f41935@2022-08-25 09:15:46 +0000",
  "timestamp":"2022-08-25 14:56:12+0000",
  "ndc":"8a8294174b7ecb28014b9699220015ca_791bb52e8965445aa57e5b4f188495a2"
}';

        $this->mockResponses([
            new Response(400, ['Content-Type' => 'application/json'], $BODY),
        ]);

        $this->expectException(ApiException::class);
        $this->expectErrorMessage('[700.400.580] cannot find transaction');
        $this->api->queryPayment('doesntexist');
    }

    public function testQueryMerchant()
    {
        $BODY = '{
  "result":{
    "code":"000.000.100",
    "description":"successful request"
  },
  "buildNumber":"9a7f25e2679d81630133ac842b2597f4a8f41935@2022-08-25 09:15:46 +0000",
  "timestamp":"2022-08-25 14:24:36+0000",
  "ndc":"8a8294174b7ecb28014b9699220015ca_a8efc35bca764fcf8fb4f6711f928c0c",
  "payments":[
    {
      "id":"8ac7a4a178a6ec6e0178b0b1f6fb3adf",
      "registrationId":"8ac7a49f78a6ec6b0178b0b1f4644a3d",
      "paymentType":"DB",
      "paymentBrand":"VISA",
      "amount":"92.00",
      "currency":"EUR",
      "descriptor":"5256.4385.1634 OPP_Channel",
      "merchantTransactionId":"test123",
      "result":{
        "code":"000.100.110",
        "description":"Request successfully processed in \'Merchant in Integrator Test Mode\'"
      },
      "resultDetails":{
        "clearingInstituteName":"Elavon-euroconex_UK_Test"
      },
      "card":{
        "bin":"420000",
        "last4Digits":"0000",
        "holder":"Jane Jones",
        "expiryMonth":"05",
        "expiryYear":"2034"
      },
      "threeDSecure":{
        "eci":"07"
      },
      "customParameters":{
        "CTPE_DESCRIPTOR_TEMPLATE":""
      },
      "risk":{
        "score":"100"
      },
      "timestamp":"2021-04-08 08:55:56.575+0000"
    }
  ]
}';
        $history = [];
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'application/json'], $BODY),
        ], $history);

        $paymentList = $this->api->queryMerchant('test123');
        $this->assertSame('/v1/query?entityId=nope&merchantTransactionId=test123', $history[0]['request']->getRequestTarget());
        $this->assertTrue($paymentList->getResult()->isSuccessfullyProcessed());
        $this->assertCount(1, $paymentList->getPayments());
        $this->assertTrue($paymentList->getPayments()[0]->getResult()->isSuccessfullyProcessed());
        $this->assertSame($paymentList->getPayments()[0]->getId(), '8ac7a4a178a6ec6e0178b0b1f6fb3adf');
    }

    public function testQueryMerchantUnknown()
    {
        $BODY = '{
  "result":{
    "code":"700.400.580",
    "description":"cannot find transaction"
  },
  "buildNumber":"9a7f25e2679d81630133ac842b2597f4a8f41935@2022-08-25 09:15:46 +0000",
  "timestamp":"2022-08-25 14:54:32+0000",
  "ndc":"8a8294174b7ecb28014b9699220015ca_4ee6b3c660744f709d907f560ee5ed62"
}';
        $this->mockResponses([
            new Response(400, ['Content-Type' => 'application/json'], $BODY),
        ]);

        $this->expectException(ApiException::class);
        $this->expectErrorMessage('[700.400.580] cannot find transaction');
        $this->api->queryMerchant('doesntexist');
    }
}
