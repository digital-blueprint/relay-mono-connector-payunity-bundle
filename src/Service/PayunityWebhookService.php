<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\MonoConnectorPayunityBundle\Entity\PaymentContract;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\WebhookRequest;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PayunityWebhookService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var LoggerInterface
     */
    private $auditLogger;

    /**
     * @var ConfigurationService
     */
    protected $configurationService;

    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->auditLogger = new NullLogger();
    }

    public function setAuditLogger(LoggerInterface $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Create a webhook request, mostly for testing pruposes.
     */
    public function createRequest(PaymentContract $paymentContract, string $jsonPayload): Request
    {
        $ivLen = \openssl_cipher_iv_length('aes-256-gcm');
        $iv = \openssl_random_pseudo_bytes($ivLen);
        $key = @hex2bin($paymentContract->getWebhookSecret());
        if ($key === false) {
            throw new \RuntimeException('invalid secret');
        }
        $encrypted = \openssl_encrypt($jsonPayload, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($encrypted === false) {
            throw new \RuntimeException();
        }
        $request = new Request([], [], [], [], [], [], bin2hex($encrypted));
        $request->headers->set('X-Initialization-Vector', bin2hex($iv));
        $request->headers->set('X-Authentication-Tag', bin2hex($tag));

        return $request;
    }

    public function decryptRequest(PaymentContract $paymentContract, Request $request): WebhookRequest
    {
        $ivHex = $request->headers->get('X-Initialization-Vector');
        if ($ivHex === null) {
            throw new BadRequestHttpException('Missing X-Initialization-Vector header');
        }
        $authTagHex = $request->headers->get('X-Authentication-Tag');
        if ($authTagHex === null) {
            throw new BadRequestHttpException('Missing X-Authentication-Tag header');
        }
        $dataHex = $request->getContent();
        if ($dataHex === null) {
            throw new BadRequestHttpException('Missing payload');
        }

        $this->auditLogger->debug('payunity: webhook request hex-data', [
            'iv' => $ivHex,
            'authTag' => $authTagHex,
            'data' => $dataHex,
        ]);

        $iv = @hex2bin($ivHex);
        if ($iv === false) {
            throw new BadRequestHttpException('Invalid value for X-Initialization-Vector header');
        }
        $authTag = @hex2bin($authTagHex);
        if ($authTag === false) {
            throw new BadRequestHttpException('Invalid value for X-Authentication-Tag header');
        }
        $data = @hex2bin($dataHex);
        if ($data === false) {
            throw new BadRequestHttpException('Invalid request body');
        }

        $key = @hex2bin($paymentContract->getWebhookSecret());
        if ($key === false) {
            throw new \RuntimeException('invalid secret');
        }

        $json = \openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $authTag);
        if ($json === false) {
            $this->auditLogger->debug('payunity: webhook decryption failed');
            throw new BadRequestHttpException('Invalid request');
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('json decode failed', ['exception' => $e]);
            throw new BadRequestHttpException('Invalid webhook payload');
        }
        $this->auditLogger->debug('payunity: webhook request data', $data);

        if (!is_string($data['type'] ?? null) || !is_array($data['payload'] ?? null)) {
            throw new BadRequestHttpException('Invalid webhook payload');
        }

        return new WebhookRequest($data['type'], $data['action'] ?? null, $data['payload']);
    }
}
