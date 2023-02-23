<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

use GuzzleHttp\Exception\RequestException;
use League\Uri\UriTemplate;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PayUnityApi implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $auditLogger;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->logger = new NullLogger();
        $this->auditLogger = new NullLogger();
    }

    public function setAuditLogger(LoggerInterface $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Prepare a checkout. See https://www.payunity.com/reference/parameters#basic.
     *
     * @param $amount - Indicates the amount of the payment request. The dot is used as decimal separator.
     * @param $currency - The currency code of the payment request's amount (ISO 4217)
     * @param $paymentType - See PaymentType
     * @param $extra - extra key/value pairs passed to the API, see the docs
     */
    public function prepareCheckout(string $amount, string $currency, string $paymentType, array $extra = []): Checkout
    {
        $uriTemplate = new UriTemplate('v1/checkouts');
        $uri = (string) $uriTemplate->expand();
        $client = $this->connection->getClient();

        $data = [
            'entityId' => $this->connection->getEntityId(),
            'amount' => $amount,
            'currency' => $currency,
            'paymentType' => $paymentType,
        ];

        foreach ($extra as $key => $value) {
            if (array_key_exists($key, $data)) {
                throw new \RuntimeException("$key already set");
            }
            $data[$key] = $value;
        }

        $this->auditLogger->debug('payunity: prepare checkout', $data);

        try {
            $response = $client->post(
                $uri, [
                    'form_params' => $data,
                ]
            );
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $checkout = $this->parseCheckoutResponse($response);

        return $checkout;
    }

    /**
     * The absolute JS script URL that needs to be used for the frontend form.
     */
    public function getPaymentScriptSrc(string $checkoutId): string
    {
        $uriTemplate = new UriTemplate($this->connection->getBaseUri().'v1/paymentWidgets.js{?checkoutId}');
        $uri = (string) $uriTemplate->expand([
            'checkoutId' => $checkoutId,
        ]);

        return $uri;
    }

    /**
     * Get the payment status. See https://www.payunity.com/tutorials/integration-guide.
     *
     * Once a status response is successful the checkout identifier can't be used anymore.
     * A throttling rule applies for get payment status calls. Per checkout,
     * it is allowed to send two get payment requests in a minute.
     */
    public function getPaymentStatus(string $checkoutId): PaymentData
    {
        $connection = $this->connection;
        $client = $this->connection->getClient();
        $entityId = $connection->getEntityId();

        $this->auditLogger->debug('payunity: get payment status');

        $uriTemplate = new UriTemplate('v1/checkouts/{checkoutId}/payment{?entityId}');
        $uri = (string) $uriTemplate->expand([
            'checkoutId' => $checkoutId,
            'entityId' => $entityId,
        ]);

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $paymentData = $this->parseGetPaymentStatusResponse($response);

        return $paymentData;
    }

    /**
     * Get a report with the details of an existing payment.
     * See https://www.payunity.com/tutorials/reporting/transaction.
     *
     * @param $paymentId - The payment ID you get via getPaymentStatus()
     */
    public function queryPayment(string $paymentId): PaymentData
    {
        $connection = $this->connection;
        $client = $this->connection->getClient();
        $entityId = $connection->getEntityId();

        $this->auditLogger->debug('payunity: query payment');

        $uriTemplate = new UriTemplate('v1/query/{paymentId}{?entityId}');
        $uri = (string) $uriTemplate->expand([
            'paymentId' => $paymentId,
            'entityId' => $entityId,
        ]);

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $paymentData = $this->parseGetPaymentStatusResponse($response);

        return $paymentData;
    }

    /**
     * Get a report with the details payments for a specific merchant. You need to pass a merchantTransactionId
     * to prepareCheckout() if you want to query them with this later on
     * See https://www.payunity.com/tutorials/reporting/transaction for this report and
     * https://www.payunity.com/reference/parameters#basic for the merchantTransactionId.
     *
     * @param $merchantTransactionId - Merchant-provided reference number
     */
    public function queryMerchant(string $merchantTransactionId): PaymentList
    {
        $connection = $this->connection;
        $client = $this->connection->getClient();
        $entityId = $connection->getEntityId();

        $this->auditLogger->debug('payunity: query merchant');

        $uriTemplate = new UriTemplate('v1/query{?entityId,merchantTransactionId}');
        $uri = (string) $uriTemplate->expand([
            'merchantTransactionId' => $merchantTransactionId,
            'entityId' => $entityId,
        ]);

        try {
            $response = $client->get($uri);
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $paymentList = $this->parseGetPaymentListResponse($response);

        return $paymentList;
    }

    private function parseGetPaymentListResponse(ResponseInterface $response): PaymentList
    {
        $json = (string) $response->getBody();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->auditLogger->debug('payunity: get payment list response', $data);

        $paymentList = new PaymentList();
        $paymentList->fromJsonResponse($data);

        return $paymentList;
    }

    private function parseGetPaymentStatusResponse(ResponseInterface $response): PaymentData
    {
        $json = (string) $response->getBody();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->anonymizePaymentResponse($data);
        $this->auditLogger->debug('payunity: get payment status response', $data);

        $paymentData = new PaymentData();
        $paymentData->fromJsonResponse($data);

        return $paymentData;
    }

    private function createResponseError(RequestException $e): ApiException
    {
        $response = $e->getResponse();
        if ($response === null) {
            return new ApiException('Unknown error');
        }
        $json = (string) $response->getBody();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->auditLogger->debug('payunity: parse error response', $data);
        $result = $data['result'];
        $code = $result['code'];
        $description = $result['description'];
        $message = "[$code] $description";
        $exc = new ApiException($message);
        $exc->result = new ResultCode($code, $description);

        return $exc;
    }

    private function parseCheckoutResponse(ResponseInterface $response): Checkout
    {
        $json = (string) $response->getBody();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->auditLogger->debug('payunity: checkout data response', $data);

        $checkout = new Checkout();
        $checkout->fromJsonResponse($data);

        return $checkout;
    }

    private function anonymizePaymentResponse(array &$data)
    {
        if (array_key_exists('bankAccount', $data)) {
            $this->recursiveAnonymize($data['bankAccount']);
        }
        if (array_key_exists('card', $data)) {
            $this->recursiveAnonymize($data['card']);
        }
    }

    private function recursiveAnonymize(array &$array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->recursiveAnonymize($array[$key]);
            } else {
                $array[$key] = str_pad('', strlen($value), '*');
            }
        }
    }
}
