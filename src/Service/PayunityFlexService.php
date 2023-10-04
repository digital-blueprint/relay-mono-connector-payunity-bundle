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

    public function start(PaymentPersistence $paymentPersistence): StartResponseInterface
    {
        $this->payunity->startPayment($paymentPersistence);
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

    public function complete(PaymentPersistence $paymentPersistence): CompleteResponseInterface
    {
        $this->payunity->updatePaymentStatus($paymentPersistence);

        return new CompleteResponse($paymentPersistence->getReturnUrl());
    }

    public function cleanup(PaymentPersistence $paymentPersistence): bool
    {
        $this->payunity->cleanupPaymentData($paymentPersistence);

        return true;
    }
}
