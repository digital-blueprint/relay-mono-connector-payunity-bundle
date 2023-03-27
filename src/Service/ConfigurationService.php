<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\MonoConnectorPayunityBundle\Entity\PaymentContract;

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
}
