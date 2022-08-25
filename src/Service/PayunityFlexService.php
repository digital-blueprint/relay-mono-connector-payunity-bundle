<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\MonoBundle\Entity\Payment;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponseInterface;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponseInterface;
use Dbp\Relay\MonoBundle\Service\PaymentServiceProviderServiceInterface;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Connection;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PaymentData;
use GuzzleHttp\Exception\RequestException;
use League\Uri\UriTemplate;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;

class PayunityFlexService implements PaymentServiceProviderServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

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

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        LoggerInterface $logger,
        PaymentDataService $paymentDataService,
        UrlHelper $urlHelper,
        RequestStack $requestStack
    ) {
        $this->paymentDataService = $paymentDataService;
        $this->urlHelper = $urlHelper;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
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
        $widgetUrl = $this->getWidgetUrl($payment);
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

        $this->connection[$contract]->setLogger($this->logger);

        return $this->connection[$contract];
    }

    public function postPaymentData(string $contract, array $data): ?PaymentData
    {
        $paymentData = null;

        $connection = $this->getConnectionByContract($contract);

        $entityId = $connection->getEntityId();
        $data['entityId'] = $entityId;
        $this->logger->debug('payunity flex service: post payment data request', $data);

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
            $response = $e->getResponse();
            $data = (string) $response->getBody();
            dump($data);
            $this->logger->error('Communication error with payment service provider!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with payment service provider!');
        }

        return $paymentData;
    }

    private function parsePostPaymentDataResponse(ResponseInterface $response): PaymentData
    {
        $json = (string) $response->getBody();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->logger->debug('payunity flex service: post payment data response', $data);

        $paymentData = new PaymentData();
        $paymentData->fromJsonResponse($data);

        return $paymentData;
    }

    private function getWidgetUrl(PaymentPersistence $payment): string
    {
        $contract = $payment->getPaymentContract();
        $method = $payment->getPaymentMethod();
        $contractConfig = $this->config['payment_contracts'][$contract];
        $config = $contractConfig['payment_methods_to_widgets'][$method];

        $request = $this->requestStack->getCurrentRequest();
        $locale = ($request !== null) ? $request->getLocale() : \Locale::getDefault();

        $uriTemplate = new UriTemplate($config['widget_url']);
        $uri = (string) $uriTemplate->expand([
            'identifier' => $payment->getIdentifier(),
            'lang' => \Locale::getPrimaryLanguage($locale) ?? 'en',
        ]);
        $uri = $this->urlHelper->getAbsoluteUrl($uri);

        return $uri;
    }

    public function complete(PaymentPersistence &$payment, string $pspData): CompleteResponseInterface
    {
        $contract = $payment->getPaymentContract();
        $paymentDataPersisted = $this->paymentDataService->getByPaymentIdentifier($payment->getIdentifier());

        if ($payment->getPaymentStatus() === Payment::PAYMENT_STATUS_COMPLETED) {
            //$paymentData = $this->getQueryPaymentData($contract, $paymentDataPersisted->getPspIdentifier());
        } else {
            $paymentData = $this->getCheckoutPaymentData($contract, $paymentDataPersisted->getPspIdentifier());

            // https://payunity.docs.oppwa.com/reference/resultCodes
            $code = $paymentData->getCode();
            if (
                preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $code)
                || preg_match('/^(000\.400\.0[^3]|000\.400\.[0-1]{2}0)/', $code)
            ) {
                $payment->setPaymentStatus(Payment::PAYMENT_STATUS_COMPLETED);
                $completedAt = new \DateTime();
                $payment->setCompletedAt($completedAt);
            } elseif (
                preg_match('/^(000\.200)/', $code)
                || preg_match('/^(800\.400\.5|100\.400\.500)/', $code)
            ) {
                $payment->setPaymentStatus(Payment::PAYMENT_STATUS_PENDING);
            } else {
                $payment->setPaymentStatus(Payment::PAYMENT_STATUS_FAILED);
            }
        }

        $completeResponse = new CompleteResponse($payment->getReturnUrl());

        return $completeResponse;
    }

    private function getCheckoutPaymentData(string $contract, string $id): ?PaymentData
    {
        $paymentData = null;

        $connection = $this->getConnectionByContract($contract);
        $client = $connection->getClient();

        $entityId = $connection->getEntityId();

        $uriTemplate = new UriTemplate('/v1/checkouts/{id}/payment?entityId={entityId}');
        $uri = (string) $uriTemplate->expand([
            'id' => $id,
            'entityId' => $entityId,
        ]);
        try {
            $response = $client->get(
                $uri
            );
            $paymentData = $this->parseGetPaymentDataResponse($response);
        } catch (RequestException $e) {
            $this->logger->error('Communication error with payment service provider!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with payment service provider!');
        }

        return $paymentData;
    }

    private function getQueryPaymentData(string $contract, string $id): ?PaymentData
    {
        $paymentData = null;

        $connection = $this->getConnectionByContract($contract);
        $client = $connection->getClient();

        $entityId = $connection->getEntityId();

        $uriTemplate = new UriTemplate('/v1/query/{id}?entityId={entityId}');
        $uri = (string) $uriTemplate->expand([
            'id' => $id,
            'entityId' => $entityId,
        ]);
        try {
            $response = $client->get(
                $uri
            );
            $paymentData = $this->parseGetPaymentDataResponse($response);
        } catch (RequestException $e) {
            $this->logger->error('Communication error with payment service provider!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with payment service provider!');
        }

        return $paymentData;
    }

    private function parseGetPaymentDataResponse(ResponseInterface $response): PaymentData
    {
        $json = (string) $response->getBody();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->logger->debug('payunity flex service: get payment data response', $data);

        $paymentData = new PaymentData();
        $paymentData->fromJsonResponse($data);

        return $paymentData;
    }

    public function cleanup(PaymentPersistence &$payment): bool
    {
        $this->paymentDataService->cleanupByPaymentIdentifier($payment->getIdentifier());

        return true;
    }
}
