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

    public function decryptRequest(PaymentContract $paymentContract, Request $request): WebhookRequest
    {
        $hexData = [
            'iv' => $request->headers->get('X-Initialization-Vector'),
            'authTag' => $request->headers->get('X-Authentication-Tag'),
            'data' => $request->getContent(),
        ];
        $this->auditLogger->debug('payunity: webhook request hex-data', $hexData);

        $hexData['key'] = $paymentContract->getWebhookSecret();

        $binData = array_map('hex2bin', $hexData);

        $json = openssl_decrypt($binData['data'], 'aes-256-gcm', $binData['key'], OPENSSL_RAW_DATA, $binData['iv'], $binData['authTag']);
        if ($json === false) {
            $this->auditLogger->debug('payunity: webhook decryption failed');
            throw new \RuntimeException('Decryption failed');
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->auditLogger->debug('payunity: webhook request data', $data);

        $webhookRequest = new WebhookRequest();
        foreach ($data as $propertyName => $propertyValue) {
            $setter = 'set'.ucfirst($propertyName);
            $webhookRequest->{$setter}($propertyValue);
        }

        return $webhookRequest;
    }
}
