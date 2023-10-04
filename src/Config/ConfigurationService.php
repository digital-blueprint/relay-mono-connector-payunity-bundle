<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Config;

class ConfigurationService
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
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

    public function checkConfig()
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
