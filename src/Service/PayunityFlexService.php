<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponseInterface;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponseInterface;
use Dbp\Relay\MonoBundle\Service\PaymentServiceProviderServiceInterface;
use Dbp\Relay\MonoConnectorPayunityBundle\Api\Connection;
use Dbp\Relay\MonoConnectorPayunityBundle\Api\PaymentData;
use GuzzleHttp\Exception\RequestException;
use League\Uri\UriTemplate;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;

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

    /**
     * @var UrlHelper
     */
    private $urlHelper;

    public function __construct(
        PaymentDataService $paymentDataService,
        UrlHelper $urlHelper
    ) {
        $this->paymentDataService = $paymentDataService;
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function start(PaymentPersistence &$payment): StartResponseInterface
    {
        $body = [
            'amount' => number_format((float) $payment->getAmount(), 2),
            'currency' => $payment->getCurrency(),
            'paymentType' => 'CD',
        ];

        $contract = $payment->getPaymentContract();
        $paymentData = $this->postPaymentData($contract, $body);
        $this->paymentDataService->createPaymentData($payment, $paymentData);

        $widgetUrl = $this->getWidgetUrl($payment, $paymentData);
        $data = null;
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
            $paymentData = $this->parsePostPaymentDataResponse($response);
        } catch (RequestException $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with payment service provider!', 'mono:psp-communication-error', ['message' => $e->getMessage()]);
        }

        return $paymentData;
    }

    private function parsePostPaymentDataResponse(ResponseInterface $response): PaymentData
    {
        $json = (string) $response->getBody();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $paymentData = new PaymentData();
        $paymentData->fromJsonResponse($data);

        return $paymentData;
    }

    private function getWidgetUrl(PaymentPersistence $payment, PaymentData $paymentData): string
    {
        $contract = $payment->getPaymentContract();
        $method = $payment->getPaymentMethod();
        $contractConfig = $this->config['payment_contracts'][$contract];
        $config = $contractConfig['payment_methods_to_widgets'][$method];

        $shopperResultUrl = $payment->getReturnUrl();
        $brands = $config['brands'];
        $checkoutId = $paymentData->getId();
        $scriptSrc = $contractConfig['api_url'].'/v1/paymentWidgets.js?checkoutId='.$checkoutId;
        $pspData = null;
        $pspError = null;

        $uriTemplate = new UriTemplate($config['widget_url']);
        $uri = (string) $uriTemplate->expand([
            'shopperResultUrl' => $shopperResultUrl,
            'brands' => $brands,
            'scriptSrc' => $scriptSrc,
            'pspData' => $pspData,
            'pspError' => $pspError,
        ]);
        $uri = $this->urlHelper->getAbsoluteUrl($uri);

        return $uri;
    }

    public function complete(PaymentPersistence &$payment, string $pspData): CompleteResponseInterface
    {
        $contract = $payment->getPaymentContract();
        $paymentDataPersisted = $this->paymentDataService->getByPaymentIdentifier($payment->getIdentifier());

        $paymentData = $this->getPaymentData($contract, $paymentDataPersisted->getPspIdentifier());

        $completeResponse = new CompleteResponse($payment->getReturnUrl());

        return $completeResponse;
    }

    private function getPaymentData(string $contract, string $id): ?PaymentData
    {
        $paymentData = null;

        $connection = $this->getConnectionByContract($contract);
        $client = $connection->getClient();

        $uriTemplate = new UriTemplate('/v1/checkouts/{id}/payment');
        $uri = (string) $uriTemplate->expand([
            'id' => $id,
        ]);
        try {
            $response = $client->get(
                $uri
            );
            $paymentData = $this->parseGetPaymentDataResponse($response);
        } catch (RequestException $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with payment service provider!', 'mono:psp-communication-error', ['message' => $e->getMessage()]);
        }

        return $paymentData;
    }

    private function parseGetPaymentDataResponse(ResponseInterface $response): PaymentData
    {
        $json = (string) $response->getBody();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $paymentData = new PaymentData();
        $paymentData->fromJsonResponse($data);

        return $paymentData;
    }
}
