<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Controller;

use Dbp\Relay\MonoBundle\Service\PaymentService;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\ConfigurationService;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\PaymentDataService;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\PayunityWebhookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Webhook extends AbstractController
{
    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var PaymentDataService
     */
    private $paymentDataService;

    /**
     * @var PayunityWebhookService
     */
    private $payunityWebhookService;

    public function __construct(
        ConfigurationService $configurationService,
        PaymentService $paymentService,
        PaymentDataService $paymentDataService,
        PayunityWebhookService $payunityWebhookService
    ) {
        $this->configurationService = $configurationService;
        $this->paymentService = $paymentService;
        $this->paymentDataService = $paymentDataService;
        $this->payunityWebhookService = $payunityWebhookService;
    }

    public function index(Request $request, string $contract): Response
    {
        $paymentContract = $this->configurationService->getPaymentContractByIdentifier($contract);
        $webhookRequest = $this->payunityWebhookService->decryptRequest(
            $paymentContract,
            $request
        );

        $pspDataArray = $webhookRequest->getPayload();
        $identifier = $pspDataArray['merchantTransactionId'];

        // fallback, if merchantTransactionId is not submitted
        if (!$identifier) {
            $checkoutId = $pspDataArray['ndc'];
            $paymentData = $this->paymentDataService->getByCheckoutId($checkoutId);
            $identifier = $paymentData->getPaymentIdentifier();
        }

        $pspData = json_encode($pspDataArray);
        $this->paymentService->completePayAction(
            $identifier,
            $pspData
        );

        $response = new JsonResponse();

        return $response;
    }
}
