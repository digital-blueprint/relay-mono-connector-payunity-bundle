<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Entity;

class PaymentContract
{
    public const TEST_MODE_INTERNAL = 'internal';
    public const TEST_MODE_EXTERNAL = 'external';

    /**
     * @var string
     */
    private $identifier;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $entityId;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var string
     */
    private $webhookSecret;

    /**
     * @var string
     */
    private $testMode;

    /**
     * @var array
     */
    private $paymentMethodsToWidgets;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(string $apiUrl): self
    {
        $this->apiUrl = $apiUrl;

        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }

    public function setWebhookSecret(string $webhookSecret): self
    {
        $this->webhookSecret = $webhookSecret;

        return $this;
    }

    public function getTestMode(): string
    {
        return $this->testMode;
    }

    public function setTestMode(string $testMode): self
    {
        $this->testMode = $testMode;

        return $this;
    }

    public function getPaymentMethodsToWidgets(): array
    {
        return $this->paymentMethodsToWidgets;
    }

    public function setPaymentMethodsToWidgets(array $paymentMethodsToWidgets): self
    {
        $this->paymentMethodsToWidgets = $paymentMethodsToWidgets;

        return $this;
    }

    public static function fromConfig(string $identifier, array $config): PaymentContract
    {
        $paymentContract = new PaymentContract();
        $paymentContract->setIdentifier($identifier);
        $paymentContract->setApiUrl((string) $config['api_url']);
        $paymentContract->setEntityId((string) $config['entity_id']);
        $paymentContract->setAccessToken((string) $config['access_token']);
        $paymentContract->setWebhookSecret($config['webhook_secret'] ?? '');
        $paymentContract->setTestMode((string) $config['test_mode']);
        $paymentContract->setPaymentMethodsToWidgets((array) $config['payment_methods_to_widgets']);

        return $paymentContract;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->identifier;
    }
}
