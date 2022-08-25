<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class PayUnityApi implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->logger = new NullLogger();
    }

    /**
     * Prepare a checkout. See https://www.payunity.com/reference/parameters#basic.
     *
     * @param $amount - Indicates the amount of the payment request. The dot is used as decimal separator.
     * @param $currency - The currency code of the payment request's amount (ISO 4217)
     * @param $paymentType - See PaymentType
     * @param $extra - extra key/value pairs passed to the API, see the docs
     */
    public function prepareCheckout(string $amount, string $currency, string $paymentType, array $extra = []): PaymentData
    {
        $uri = '/v1/checkouts';
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

        try {
            $response = $client->post(
                $uri, [
                    'form_params' => $data,
                ]
            );
        } catch (RequestException $e) {
            throw self::createResponseError($e);
        }

        $paymentData = $this->parsePostPaymentDataResponse($response);

        return $paymentData;
    }

    private static function createResponseError(RequestException $e): ApiException
    {
        $response = $e->getResponse();
        if ($response === null) {
            return new ApiException('Unknown error');
        }
        $data = (string) $response->getBody();
        $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        $result = $json['result'];
        $code = $result['code'];
        $description = $result['description'];
        $message = "[$code] $description";

        return new ApiException($message);
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
}
