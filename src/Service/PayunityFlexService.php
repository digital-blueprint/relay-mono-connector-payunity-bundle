<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\CompleteResponseInterface;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponse;
use Dbp\Relay\MonoBundle\PaymentServiceProvider\StartResponseInterface;
use Dbp\Relay\MonoBundle\Service\PaymentServiceProviderServiceInterface;
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

    public function start(PaymentPersistence &$payment): StartResponseInterface
    {
        $widgetUrl = $this->payunity->getWidgetUrl($payment);
        $data = null;
        $error = null;

        return new StartResponse(
            $widgetUrl,
            $data,
            $error
        );
    }

    public function complete(PaymentPersistence &$payment, string $pspData): CompleteResponseInterface
    {
        $this->payunity->checkComplete($payment);

        return new CompleteResponse($payment->getReturnUrl());
    }

    public function cleanup(PaymentPersistence &$payment): bool
    {
        $this->payunity->cleanupPaymentData($payment);

        return true;
    }
}
