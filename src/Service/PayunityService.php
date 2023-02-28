<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\MonoBundle\Entity\Payment;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\ApiException;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Checkout;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Connection;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PaymentData;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PayUnityApi;
use League\Uri\UriTemplate;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Uid\Uuid;

class PayunityService implements LoggerAwareInterface
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
    /**
     * @var LoggerInterface
     */
    private $auditLogger;

    public function __construct(
        PaymentDataService $paymentDataService,
        UrlHelper $urlHelper,
        RequestStack $requestStack,
        Locale $locale
    ) {
        $this->paymentDataService = $paymentDataService;
        $this->urlHelper = $urlHelper;
        $this->requestStack = $requestStack;
        $this->locale = $locale;
        $this->logger = new NullLogger();
        $this->auditLogger = new NullLogger();
    }

    public function setAuditLogger(LoggerInterface $auditLogger)
    {
        $this->auditLogger = $auditLogger;
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
        $api = $this->getApiByContract($contract, null);
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

    private function getLoggingContext(PaymentPersistence $payment): array
    {
        return ['relay-mono-payment-id' => $payment->getIdentifier()];
    }

    public function getApiByContract(string $contract, ?PaymentPersistence $payment): PayUnityApi
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
        $api->setAuditLogger($this->auditLogger);
        if ($payment !== null) {
            $api->setLoggingContext($this->getLoggingContext($payment));
        }

        return $api;
    }

    public function getPaymentScriptSrc(PaymentPersistence $payment, string $contract, string $checkoutId): string
    {
        $api = $this->getApiByContract($contract, $payment);

        return $api->getPaymentScriptSrc($checkoutId);
    }

    public function prepareCheckout(PaymentPersistence $payment, string $contract, string $amount, string $currency, string $paymentType, array $extra = []): Checkout
    {
        $api = $this->getApiByContract($contract, $payment);

        try {
            $checkout = $api->prepareCheckout($amount, $currency, $paymentType, $extra);
        } catch (ApiException $e) {
            $this->logger->error('Communication error with payment service provider!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with payment service provider!');
        }

        $this->paymentDataService->createPaymentData($payment, $checkout);

        return $checkout;
    }

    public function getWidgetUrl(PaymentPersistence $payment): string
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

    public function checkComplete(PaymentPersistence $payment): void
    {
        $contract = $payment->getPaymentContract();
        $paymentDataPersisted = $this->paymentDataService->getByPaymentIdentifier($payment->getIdentifier());

        if ($payment->getPaymentStatus() === Payment::PAYMENT_STATUS_COMPLETED) {
        } else {
            $paymentData = $this->getCheckoutPaymentData($payment, $contract, $paymentDataPersisted->getPspIdentifier());

            // https://payunity.docs.oppwa.com/reference/resultCodes
            $result = $paymentData->getResult();

            if ($result->isSuccessfullyProcessed() || $result->isSuccessfullyProcessedNeedsManualReview()) {
                $this->auditLogger->debug('payunity: Setting payment to complete', $this->getLoggingContext($payment));
                $payment->setPaymentStatus(Payment::PAYMENT_STATUS_COMPLETED);
                $completedAt = new \DateTime();
                $payment->setCompletedAt($completedAt);
            } elseif ($result->isPending() || $result->isPendingExtra()) {
                $this->auditLogger->debug('payunity: Setting payment to pending', $this->getLoggingContext($payment));
                $payment->setPaymentStatus(Payment::PAYMENT_STATUS_PENDING);
            } else {
                $this->auditLogger->debug('payunity: Setting payment to failed', $this->getLoggingContext($payment));
                $payment->setPaymentStatus(Payment::PAYMENT_STATUS_FAILED);
            }
        }
    }

    /**
     * Delete everything related to the passed payment in the connector.
     */
    public function cleanupPaymentData(PaymentPersistence $payment): void
    {
        $this->auditLogger->debug('payunity: clean up payment data', $this->getLoggingContext($payment));
        $this->paymentDataService->cleanupByPaymentIdentifier($payment->getIdentifier());
    }

    private function getCheckoutPaymentData(PaymentPersistence $payment, string $contract, string $checkoutId): PaymentData
    {
        $api = $this->getApiByContract($contract, $payment);
        try {
            return $api->getPaymentStatus($checkoutId);
        } catch (ApiException $e) {
            $this->logger->error('Communication error with payment service provider!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with payment service provider!');
        }
    }
}
