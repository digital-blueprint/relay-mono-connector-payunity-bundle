<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Config;

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
     * @var ?string
     */
    private $webhookSecret;

    /**
     * @var ?string
     */
    private $testMode;

    /**
     * @var array<string,PaymentMethod>
     */
    private $paymentMethods;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(string $apiUrl): void
    {
        $this->apiUrl = $apiUrl;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): void
    {
        $this->entityId = $entityId;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    public function setWebhookSecret(?string $webhookSecret): void
    {
        $this->webhookSecret = $webhookSecret;
    }

    public function getTestMode(): ?string
    {
        return $this->testMode;
    }

    public function setTestMode(?string $testMode): void
    {
        $this->testMode = $testMode;
    }

    /**
     * @param array<string,PaymentMethod> $paymentMethods
     */
    public function setPaymentMethods(array $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @return array<string,PaymentMethod>
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    public function getPaymentMethod(string $methodId): ?PaymentMethod
    {
        return $this->paymentMethods[$methodId] ?? null;
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function fromConfig(string $identifier, array $config): PaymentContract
    {
        $paymentContract = new PaymentContract();
        $paymentContract->setIdentifier($identifier);
        $paymentContract->setApiUrl($config['api_url']);
        $paymentContract->setEntityId($config['entity_id']);
        $paymentContract->setAccessToken($config['access_token']);
        $paymentContract->setWebhookSecret($config['webhook_secret']);
        $paymentContract->setTestMode($config['test_mode']);
        $paymentMethods = [];
        foreach ($config['payment_methods'] as $id => $paymentMethodConfig) {
            $paymentMethod = new PaymentMethod();
            $paymentMethod->setIdentifier($id);
            $paymentMethod->setBrands($paymentMethodConfig['brands']);
            $paymentMethods[$id] = $paymentMethod;
        }
        $paymentContract->setPaymentMethods($paymentMethods);

        return $paymentContract;
    }
}
