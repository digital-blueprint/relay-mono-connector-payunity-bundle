<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Tests;

use Dbp\Relay\MonoConnectorPayunityBundle\Config\PaymentContract;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\WebhookRequest;
use Dbp\Relay\MonoConnectorPayunityBundle\Webhook\PayunityWebhookService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PayunityWebhookServiceTest extends TestCase
{
    // taken from https://www.payunity.com/tutorials/webhooks/integration-guide
    private const EXAMPLE_PAYLOAD = '
    {
   "type":"PAYMENT",
   "payload":{
      "id":"8a829449515d198b01517d5601df5584",
      "paymentType":"PA",
      "paymentBrand":"VISA",
      "amount":"92.00",
      "currency":"EUR",
      "presentationAmount":"92.00",
      "presentationCurrency":"EUR",
      "descriptor":"3017.7139.1650 OPP_Channel ",
      "result":{
         "code":"000.100.110",
         "description":"Request successfully processed in \'Merchant in Integrator Test Mode\'"
      },
      "authentication":{
         "entityId":"8a8294185282b95b01528382b4940245"
      },
      "card":{
         "bin":"420000",
         "last4Digits":"0000",
         "holder":"Jane Jones",
         "expiryMonth":"05",
         "expiryYear":"2018"
      },
      "customer":{
         "givenName":"Jones",
         "surname":"Jane",
         "merchantCustomerId":"jjones",
         "sex":"F",
         "email":"jane@jones.com"
      },
      "customParameters":{
         "SHOPPER_promoCode":"AT052"
      },
      "risk":{
         "score":"0"
      },
      "buildNumber":"ec3c704170e54f6d7cf86c6f1969b20f6d855ce5@2015-12-01 12:20:39 +0000",
      "timestamp":"2015-12-07 16:46:07+0000",
      "ndc":"8a8294174b7ecb28014b9699220015ca_66b12f658442479c8ca66166c4999e78"
   }
}';

    // This gets sent to the webhook when the admin tests it via the BIP/PU.MA web interface
    private const INTEGRATION_TEST_MODE_PAYLOAD = '
{
    "type": "test",
    "action": "webhook activation",
    "payload": {
        "result": {
            "code": "000.100.110",
            "description": "Request successfully processed in \'Merchant in Integrator Test Mode\'"
        }
    }
}
    ';

    // This is a real payload we got, using test data only, partly anonymized.
    // This contains "merchantTransactionId" for example, which we set, but isn't in the example from the docs
    private const REAL_PAYLOAD = '
  {
    "type": "PAYMENT",
    "payload": {
        "id": "8ac7a4a28751bbd2018756de795a02eb",
        "paymentType": "DB",
        "paymentBrand": "VISA",
        "amount": "20.20",
        "currency": "EUR",
        "presentationAmount": "20.20",
        "presentationCurrency": "EUR",
        "descriptor": "2526.4602.5082 flex.something.something ",
        "merchantTransactionId": "792217c5-cf4c-45a4-bc9b-17ba8d7a6926",
        "result": {
            "code": "000.100.112",
            "description": "Request successfully processed in \'Merchant in Connector Test Mode\'",
            "randomField2071906327": "Please allow for new unexpected fields to be added"
        },
        "resultDetails": {
            "ExtendedDescription": "Approved or completed successfully",
            "AcquirerResponse": "00",
            "reconciliationId": "(removed)",
            "ConnectorTxID1": "394156",
            "ConnectorTxID3": "71E00629"
        },
        "card": {
            "bin": "420000",
            "last4Digits": "0000",
            "holder": "adad",
            "expiryMonth": "12",
            "expiryYear": "2042"
        },
        "customer": {
            "ip": "(removed)"
        },
        "authentication": {
            "entityId": "(removed)"
        },
        "customParameters": {
            "SHOPPER_EndToEndIdentity": "(removed)",
            "CTPE_DESCRIPTOR_TEMPLATE": ""
        },
        "redirect": {
            "parameters": []
        },
        "risk": {
            "score": "100"
        },
        "timestamp": "2023-04-06 14:00:33+0000",
        "ndc": "(removed).uat01-vm-tx04",
        "merchantAccountId": "(removed)",
        "channelName": "flex.something.something",
        "source": "OPPUI",
        "loyaltyIndicator": "(removed)"
    }
}
    ';

    public function testDecrypt()
    {
        $secret = 'foobar';
        $contract = new PaymentContract();
        $contract->setWebhookSecret(bin2hex($secret));

        $service = new PayunityWebhookService();
        $request = $service->createRequest($contract, self::EXAMPLE_PAYLOAD);
        $result = $service->decryptRequest($contract, $request);
        $this->assertSame(WebhookRequest::TYPE_PAYMENT, $result->getType());
        $pspDataArray = $result->getPayload();
        $this->assertSame('8a8294174b7ecb28014b9699220015ca_66b12f658442479c8ca66166c4999e78', $pspDataArray['ndc']);
    }

    public function testMissingInput()
    {
        $service = new PayunityWebhookService();
        $request = new Request();
        $contract = new PaymentContract();
        $contract->setWebhookSecret(bin2hex('foobar'));
        $this->expectException(BadRequestHttpException::class);
        $service->decryptRequest($contract, $request);
    }

    public function testWrongSecret()
    {
        $secret = 'foobar';
        $service = new PayunityWebhookService();

        $contract = new PaymentContract();
        $contract->setWebhookSecret(bin2hex($secret));
        $request = $service->createRequest($contract, self::EXAMPLE_PAYLOAD);

        $contract = new PaymentContract();
        $contract->setWebhookSecret(bin2hex('this-is-the-wrong-one'));

        $this->expectException(BadRequestHttpException::class);
        $service->decryptRequest($contract, $request);
    }

    public function testInvalidPayload()
    {
        $secret = 'foobar';

        $contract = new PaymentContract();
        $contract->setWebhookSecret(bin2hex($secret));

        $service = new PayunityWebhookService();
        $request = $service->createRequest($contract, 'nope');
        $this->expectException(BadRequestHttpException::class);
        $service->decryptRequest($contract, $request);
    }

    public function testMissingPayloadKeys()
    {
        $secret = 'foobar';

        $contract = new PaymentContract();
        $contract->setWebhookSecret(bin2hex($secret));

        $service = new PayunityWebhookService();
        $request = $service->createRequest($contract, '{}');
        $this->expectException(BadRequestHttpException::class);
        $service->decryptRequest($contract, $request);
    }

    public function testInvalidIv()
    {
        $secret = 'foobar';

        $contract = new PaymentContract();
        $contract->setWebhookSecret(bin2hex($secret));

        $service = new PayunityWebhookService();
        $request = $service->createRequest($contract, '{}');
        $request->headers->set('X-Initialization-Vector', 'invalid');
        $this->expectException(BadRequestHttpException::class);
        $service->decryptRequest($contract, $request);
    }

    public function testIntegrationTestsPayload()
    {
        $secret = 'foobar';

        $contract = new PaymentContract();
        $contract->setWebhookSecret(bin2hex($secret));

        $service = new PayunityWebhookService();
        $request = $service->createRequest($contract, self::INTEGRATION_TEST_MODE_PAYLOAD);
        $result = $service->decryptRequest($contract, $request);
        $this->assertSame(WebhookRequest::TYPE_TEST, $result->getType());
    }

    public function testRealPayload()
    {
        $secret = 'foobar';

        $contract = new PaymentContract();
        $contract->setWebhookSecret(bin2hex($secret));

        $service = new PayunityWebhookService();
        $request = $service->createRequest($contract, self::REAL_PAYLOAD);
        $result = $service->decryptRequest($contract, $request);
        $this->assertSame(WebhookRequest::TYPE_PAYMENT, $result->getType());
        $this->assertSame('792217c5-cf4c-45a4-bc9b-17ba8d7a6926', $result->getPayload()['merchantTransactionId']);
    }
}
