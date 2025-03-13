<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Config;

class ConfigurationService
{
    /**
     * @var mixed[]
     */
    private $config = [];

    /**
     * @param mixed[] $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getPaymentContractByIdentifier(string $identifier): ?PaymentContract
    {
        $paymentContract = null;

        if (array_key_exists($identifier, $this->config['payment_contracts'])) {
            $paymentContractConfig = $this->config['payment_contracts'][$identifier];
            $paymentContract = PaymentContract::fromConfig($identifier, $paymentContractConfig);
        }

        return $paymentContract;
    }

    public function checkConfig(): void
    {
        foreach ($this->getPaymentContracts() as $contract) {
            $secret = $contract->getWebhookSecret();
            // make sure the secret is in the right format
            if (@hex2bin($secret) === false) {
                throw new \RuntimeException('Invalid webhook secret format');
            }
        }
    }

    /**
     * @return PaymentContract[]
     */
    public function getPaymentContracts(): array
    {
        $contracts = [];
        $config = $this->config['payment_contracts'] ?? [];
        foreach ($config as $identifier => $paymentContractConfig) {
            $contracts[] = PaymentContract::fromConfig($identifier, $paymentContractConfig);
        }

        return $contracts;
    }
}
