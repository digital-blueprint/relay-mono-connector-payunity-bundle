<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Config;

use Dbp\Relay\MonoConnectorPayunityBundle\Controller\Widget;

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

    public function checkConfig(string $contractId): void
    {
        foreach ($this->getPaymentContracts() as $contract) {
            if ($contract->getIdentifier() === $contractId) {
                $secret = $contract->getWebhookSecret();
                // make sure the secret is in the right format
                if ($secret !== null && @hex2bin($secret) === false) {
                    throw new \RuntimeException('Invalid webhook secret format');
                }

                // make sure the template can be dervived from the config
                foreach ($contract->getPaymentMethods() as $paymentMethod) {
                    Widget::getTemplateForBrands($paymentMethod->getIdentifier(), $paymentMethod->getBrands());
                }
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
