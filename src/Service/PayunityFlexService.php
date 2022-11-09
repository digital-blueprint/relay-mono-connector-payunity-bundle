<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\MonoBundle\Entity\Payment;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponseInterface;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponseInterface;
use Dbp\Relay\MonoBundle\Service\PaymentServiceProviderServiceInterface;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\ApiException;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Checkout;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Connection;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PaymentData;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PayUnityApi;
use League\Uri\UriTemplate;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Uid\Uuid;

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

    /**
     * @var Locale
     */
    private $locale;

    public function __construct(
        LoggerInterface $logger,
        PaymentDataService $paymentDataService,
        UrlHelper $urlHelper,
        RequestStack $requestStack,
        Locale $locale
    ) {
        $this->paymentDataService = $paymentDataService;
        $this->urlHelper = $urlHelper;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->locale = $locale;
    }

    /**
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return string[]
     */
    public function getContracts(): array
    {
        return array_keys($this->config['payment_contracts']);
    }

    public function checkConnection($contract): void
    {
        $api = $this->getApiByContract($contract);
        try {
            $api->queryMerchant(Uuid::v4()->toRfc4122());
        } catch (ApiException $e) {
            // [700.400.580] cannot find transaction
            // which is expected. Every other error means we couldn't connect/auth somehow.
            if ($e->result->getCode() === '700.400.580') {
                return;
            }
            throw $e;
        }
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

    private function getApiByContract(string $contract): PayUnityApi
    {
        if (!array_key_exists($contract, $this->connection)) {
            if (!array_key_exists($contract, $this->config['payment_contracts'])) {
                throw new \RuntimeException("Contract $contract doesn't exist");
            }
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

        $connection = $this->connection[$contract];
        $connection->setLogger($this->logger);
        $api = new PayUnityApi($connection);
        $api->setLogger($this->logger);

        return $api;
    }

    public function getPaymentScriptSrc(string $contract, string $checkoutId): string
    {
        $api = $this->getApiByContract($contract);

        return $api->getPaymentScriptSrc($checkoutId);
    }

    public function postPaymentData(string $contract, string $amount, string $currency, string $paymentType, array $extra = []): Checkout
    {
        $api = $this->getApiByContract($contract);

        try {
            return $api->prepareCheckout($amount, $currency, $paymentType, $extra);
        } catch (ApiException $e) {
            $this->logger->error('Communication error with payment service provider!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with payment service provider!');
        }
    }

    private function getWidgetUrl(PaymentPersistence $payment): string
    {
        $contract = $payment->getPaymentContract();
        $method = $payment->getPaymentMethod();
        $contractConfig = $this->config['payment_contracts'][$contract];
        $config = $contractConfig['payment_methods_to_widgets'][$method];

        $uriTemplate = new UriTemplate($config['widget_url']);
        $uri = (string) $uriTemplate->expand([
            'identifier' => $payment->getIdentifier(),
            'lang' => $this->locale->getCurrentPrimaryLanguage(),
        ]);
        $uri = $this->urlHelper->getAbsoluteUrl($uri);

        return $uri;
    }

    public function complete(PaymentPersistence &$payment, string $pspData): CompleteResponseInterface
    {
        $contract = $payment->getPaymentContract();
        $paymentDataPersisted = $this->paymentDataService->getByPaymentIdentifier($payment->getIdentifier());

        if ($payment->getPaymentStatus() === Payment::PAYMENT_STATUS_COMPLETED) {
        } else {
            $paymentData = $this->getCheckoutPaymentData($contract, $paymentDataPersisted->getPspIdentifier());

            // https://payunity.docs.oppwa.com/reference/resultCodes
            $result = $paymentData->getResult();

            if ($result->isSuccessfullyProcessed() || $result->isSuccessfullyProcessedNeedsManualReview()) {
                $this->logger->error('Setting payment to complete', ['id' => $payment->getIdentifier()]);
                $payment->setPaymentStatus(Payment::PAYMENT_STATUS_COMPLETED);
                $completedAt = new \DateTime();
                $payment->setCompletedAt($completedAt);
            } elseif ($result->isPending() || $result->isPendingExtra()) {
                $this->logger->error('Setting payment to pending', ['id' => $payment->getIdentifier()]);
                $payment->setPaymentStatus(Payment::PAYMENT_STATUS_PENDING);
            } else {
                $this->logger->error('Setting payment to failed', ['id' => $payment->getIdentifier()]);
                $payment->setPaymentStatus(Payment::PAYMENT_STATUS_FAILED);
            }
        }

        $completeResponse = new CompleteResponse($payment->getReturnUrl());

        return $completeResponse;
    }

    private function getCheckoutPaymentData(string $contract, string $checkoutId): PaymentData
    {
        $api = $this->getApiByContract($contract);
        try {
            return $api->getPaymentStatus($checkoutId);
        } catch (ApiException $e) {
            $this->logger->error('Communication error with payment service provider!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with payment service provider!');
        }
    }

    public function cleanup(PaymentPersistence &$payment): bool
    {
        $this->paymentDataService->cleanupByPaymentIdentifier($payment->getIdentifier());

        return true;
    }
}
