<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Controller;

use Dbp\Relay\MonoBundle\Service\PaymentService;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\WebhookRequest;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\ConfigurationService;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\PaymentDataService;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\PayunityWebhookService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Webhook extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
        $this->logger = new NullLogger();
    }

    public function index(Request $request, string $contract): Response
    {
        $response = new JsonResponse();

        $paymentContract = $this->configurationService->getPaymentContractByIdentifier($contract);
        $webhookRequest = $this->payunityWebhookService->decryptRequest(
            $paymentContract,
            $request
        );

        // In case of a test we do nothing and just return "success"
        if ($webhookRequest->getType() === WebhookRequest::TYPE_TEST) {
            $this->logger->debug('Test webhook detected, returning success');

            return $response;
        }

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

        return $response;
    }
}
