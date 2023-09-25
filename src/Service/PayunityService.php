<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoBundle\Entity\PaymentStatus;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\ApiException;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Checkout;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Connection;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PaymentData;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PaymentType;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PayUnityApi;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Tools;
use League\Uri\UriTemplate;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
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
     * @var Locale
     */
    private $locale;

    /**
     * @var LockFactory
     */
    private $lockFactory;

    /**
     * @var LoggerInterface
     */
    private $auditLogger;

    public function __construct(
        PaymentDataService $paymentDataService,
        UrlHelper $urlHelper,
        Locale $locale,
        LockFactory $lockFactory
    ) {
        $this->paymentDataService = $paymentDataService;
        $this->urlHelper = $urlHelper;
        $this->locale = $locale;
        $this->lockFactory = $lockFactory;
        $this->logger = new NullLogger();
        $this->auditLogger = new NullLogger();
    }

    public function setAuditLogger(LoggerInterface $auditLogger): void
    {
        $this->auditLogger = $auditLogger;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    private function createPaymentLock(PaymentPersistence $payment): LockInterface
    {
        $resourceKey = sprintf(
            'mono-connector-payunity-%s',
            $payment->getIdentifier()
        );

        return $this->lockFactory->createLock($resourceKey, 60, true);
    }

    /**
     * @return string[]
     */
    public function getContracts(): array
    {
        return array_keys($this->config['payment_contracts']);
    }

    public function getPaymentIdForPspData(string $pspData): ?string
    {
        // First check if the PSP data is for us, null means we don't handle it
        if (!Utils::isPayunityPspData($pspData)) {
            return null;
        }

        // Then extract the checkoudID
        $checkoutId = Utils::extractCheckoutIdFromPspData($pspData);
        if ($checkoutId === false) {
            throw new ApiError(Response::HTTP_BAD_REQUEST, 'Invalid PSP data');
        }

        $paymentDataPersistence = $this->paymentDataService->getByCheckoutId($checkoutId);
        if (!$paymentDataPersistence) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Payment data was not found!', 'mono:payment-data-not-found');
        }

        // Then map it to the payment ID and return that to the mono bundle
        return $paymentDataPersistence->getPaymentIdentifier();
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

    public function getLoggingContext(PaymentPersistence $payment): array
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

        try {
            $this->paymentDataService->createPaymentData($payment, $checkout);
        } catch (\Exception $e) {
            $this->logger->error('Payment data could not be created!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Payment data could not be created!');
        }

        return $checkout;
    }

    public function startPayment(PaymentPersistence $payment): void
    {
        $contract = $payment->getPaymentContract();
        $contractConfig = $this->config['payment_contracts'][$contract];

        $contract = $payment->getPaymentContract();
        $amount = Tools::floatToAmount((float) $payment->getAmount());
        $currency = $payment->getCurrency();
        $paymentType = PaymentType::DEBIT;
        $extra = [];
        $testMode = $contractConfig['test_mode'];
        if ($testMode === 'internal') {
            $extra['testMode'] = 'INTERNAL';
        } elseif ($testMode === 'external') {
            $extra['testMode'] = 'EXTERNAL';
        }

        // This allows us to (manually) connect our payment entry with the transaction in the payunity web interface
        // even if the payment gets canceled or never finished.
        $extra['merchantTransactionId'] = $payment->getIdentifier();

        $this->prepareCheckout($payment, $contract, $amount, $currency, $paymentType, $extra);
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

    public function updatePaymentStatus(PaymentPersistence $payment): void
    {
        $lock = $this->createPaymentLock($payment);
        $lock->acquire(true);

        $this->auditLogger->debug('payunity: Checking if payment is completed', $this->getLoggingContext($payment));

        try {
            $contract = $payment->getPaymentContract();
            $paymentDataPersisted = $this->paymentDataService->getByPaymentIdentifier($payment->getIdentifier());
            if ($paymentDataPersisted === null) {
                throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Payment data was not found!', 'mono:payment-data-not-found');
            }

            if ($payment->getPaymentStatus() === PaymentStatus::COMPLETED) {
                $this->auditLogger->debug('payunity: payment already completed, nothing to do', $this->getLoggingContext($payment));
            } else {
                $pspIdentifier = $paymentDataPersisted->getPspIdentifier();
                $this->auditLogger->debug('payunity: Found existing checkout: '.$pspIdentifier, $this->getLoggingContext($payment));
                $paymentData = $this->getCheckoutPaymentData($payment, $contract, $pspIdentifier);

                // https://payunity.docs.oppwa.com/reference/resultCodes
                $result = $paymentData->getResult();

                if ($result->isSuccessfullyProcessed() || $result->isSuccessfullyProcessedNeedsManualReview()) {
                    $this->auditLogger->debug('payunity: Setting payment to complete', $this->getLoggingContext($payment));
                    $payment->setPaymentStatus(PaymentStatus::COMPLETED);
                    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                    $payment->setCompletedAt($now);
                } elseif ($result->isPending() || $result->isPendingExtra()) {
                    $this->auditLogger->debug('payunity: Setting payment to pending', $this->getLoggingContext($payment));
                    $payment->setPaymentStatus(PaymentStatus::PENDING);
                } else {
                    $this->auditLogger->debug('payunity: Setting payment to failed', $this->getLoggingContext($payment));
                    $payment->setPaymentStatus(PaymentStatus::FAILED);
                }
            }
        } finally {
            $lock->release();
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
