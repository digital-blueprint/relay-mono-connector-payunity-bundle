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
    /**
     * @var PaymentDataService
     */
    private $paymentDataService;

    public function __construct(PaymentDataService $paymentDataService, PayunityService $payunity)
    {
        $this->payunity = $payunity;
        $this->paymentDataService = $paymentDataService;
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
        $this->paymentDataService->cleanupByPaymentIdentifier($payment->getIdentifier());

        return true;
    }
}
