<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponseInterface;
use Dbp\Relay\MonoBundle\Service\PaymentServiceProviderServiceInterface;
use Dbp\Relay\MonoConnectorPayunityBundle\Api\PaymentData;
use Dbp\Relay\MonoConnectorPayunityBundle\Api\Connection;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class PayunityFlexService implements PaymentServiceProviderServiceInterface
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @var Connection[]
     */
    private $connection = [];

    /**
     * @var PaymentDataService
     */
    private $paymentDataService;

    public function __construct(
        PaymentDataService $paymentDataService
    )
    {
        $this->paymentDataService = $paymentDataService;
    }

    /**
     * @param array $config
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function start(PaymentPersistence &$payment): StartResponseInterface
    {
        $body = [
            'amount' => number_format((float)$payment->getAmount(), 2),
            'currency' => $payment->getCurrency(),
            'paymentType' => 'CD',
        ];

        $contract = $payment->getPaymentContract();
        $paymentData = $this->postPaymentData($contract, $body);
        $this->paymentDataService->createPaymentData($payment, $paymentData);

        $widgetUrl = '';
        $data = json_encode([
            'id' => $paymentData->getId(),
        ]);
        $error = null;

        $startResponse = new StartResponse(
            $widgetUrl,
            $data,
            $error
        );

        return $startResponse;
    }

    private function getConnectionByContract(string $contract): Connection
    {
        if (
            !array_key_exists($contract, $this->connection)
            && array_key_exists($contract, $this->config['payment_contracts'])
        ) {
            $config = $this->config['payment_contracts'][$contract];

            $apiUrl = $config['api_url'];
            $entityId = $config['entity_id'];
            $accessToken = $config['access_token'];

            $this->connection[$contract] = new Connection(
                $apiUrl,
                $entityId,
                $accessToken
            );
        }

        return $this->connection[$contract];
    }

    private function postPaymentData(string $contract, array $data): ?PaymentData
    {
        $paymentData = null;

        $connection = $this->getConnectionByContract($contract);

        $entityId = $connection->getEntityId();
        $data['entityId'] = $entityId;

        $client = $connection->getClient();
        $uri = '/v1/checkouts';
        try {
            $response = $client->post(
                $uri,
                [
                    'form_params' => $data,
                ]
            );
            $paymentData = $this->parsePaymentDataResponse($response);
        }  catch (RequestException $e) {
        }

        return $paymentData;
    }

    private function parsePaymentDataResponse(ResponseInterface $response): PaymentData
    {
        $json = (string) $response->getBody();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $paymentData = new PaymentData();
        $paymentData->fromJsonResponse($data);

        return $paymentData;
    }
}
