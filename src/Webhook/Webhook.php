<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Webhook;

use Dbp\Relay\MonoBundle\Service\PaymentService;
use Dbp\Relay\MonoConnectorPayunityBundle\Config\ConfigurationService;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\WebhookRequest;
use Dbp\Relay\MonoConnectorPayunityBundle\Persistence\PaymentDataService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
        $paymentContract = $this->configurationService->getPaymentContractByIdentifier($contract);
        if ($paymentContract === null) {
            throw new BadRequestHttpException('Unknown contract: '.$contract);
        }
        $webhookRequest = $this->payunityWebhookService->decryptRequest(
            $paymentContract,
            $request
        );

        $this->logger->debug('Handling webhook of type: '.$webhookRequest->getType());

        if ($webhookRequest->getType() === WebhookRequest::TYPE_TEST) {
            // In case of a test we do nothing and just return "success"
            $this->logger->debug('Test webhook detected, returning success');

            return new JsonResponse();
        } elseif ($webhookRequest->getType() === WebhookRequest::TYPE_PAYMENT) {
            $pspDataArray = $webhookRequest->getPayload();
            $identifier = $pspDataArray['merchantTransactionId'] ?? null;

            // fallback, if merchantTransactionId is not submitted
            if (!$identifier) {
                $this->logger->debug('No merchantTransactionId found, falling back');
                $checkoutId = $pspDataArray['ndc'] ?? null;
                if ($checkoutId === null) {
                    throw new BadRequestHttpException('Checkout ID missing');
                }
                $paymentData = $this->paymentDataService->getByCheckoutId($checkoutId);
                if ($paymentData === null) {
                    throw new BadRequestHttpException('Unknown checkout ID: '.$checkoutId);
                }
                $identifier = $paymentData->getPaymentIdentifier();
            }

            $this->paymentService->completePayAction(
                $identifier
            );

            return new JsonResponse();
        } elseif ($webhookRequest->getType() === WebhookRequest::TYPE_RISK) {
            // Nothing to do
            return new JsonResponse();
        } elseif ($webhookRequest->getType() === WebhookRequest::TYPE_REGISTRATION) {
            // Nothing to do
            return new JsonResponse();
        } else {
            throw new BadRequestHttpException('Unknown webhook type: '.$webhookRequest->getType());
        }
    }
}
