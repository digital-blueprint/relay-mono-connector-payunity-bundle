<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponseInterface;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\PaymentServiceProviderServiceInterface;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponseInterface;
use Dbp\Relay\MonoBundle\Persistence\PaymentPersistence;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class PayunityFlexService implements PaymentServiceProviderServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var PayunityService
     */
    private $payunity;

    public function __construct(PayunityService $payunity)
    {
        $this->payunity = $payunity;
    }

    public function start(string $pspContract, string $pspMethod, PaymentPersistence $paymentPersistence): StartResponseInterface
    {
        $this->payunity->startPayment($pspContract, $pspMethod, $paymentPersistence);
        $widgetUrl = $this->payunity->getWidgetUrl($paymentPersistence);
        $data = null;
        $error = null;

        return new StartResponse(
            $widgetUrl,
            $data,
            $error
        );
    }

    public function getPaymentIdForPspData(string $pspData): ?string
    {
        return $this->payunity->getPaymentIdForPspData($pspData);
    }

    public function complete(string $pspContract, PaymentPersistence $paymentPersistence): CompleteResponseInterface
    {
        $this->payunity->updatePaymentStatus($pspContract, $paymentPersistence);

        return new CompleteResponse($paymentPersistence->getReturnUrl());
    }

    public function cleanup(string $pspContract, PaymentPersistence $paymentPersistence): bool
    {
        $this->payunity->cleanupPaymentData($paymentPersistence);

        return true;
    }

    public function getPspContracts(): array
    {
        $ids = [];
        foreach ($this->payunity->getContracts() as $contract) {
            $ids[] = $contract->getIdentifier();
        }

        return $ids;
    }

    public function getPspMethods(string $pspContract): array
    {
        foreach ($this->payunity->getContracts() as $contract) {
            if ($contract->getIdentifier() === $pspContract) {
                return array_keys($contract->getPaymentMethodsToWidgets());
            }
        }

        return [];
    }
}
