<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Controller;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Locale\Locale;
use Dbp\Relay\MonoBundle\Entity\PaymentStatus;
use Dbp\Relay\MonoBundle\Service\PaymentService;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\PaymentType;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Tools;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\PayunityService;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\Utils;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Widget extends AbstractController
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var PayunityService
     */
    private $payunityService;

    /**
     * @var Locale
     */
    private $locale;

    /**
     * @var LoggerInterface
     */
    private $auditLogger;

    public function __construct(
        PaymentService $paymentService,
        PayunityService $payunityService,
        Locale $locale
    ) {
        $this->paymentService = $paymentService;
        $this->payunityService = $payunityService;
        $this->locale = $locale;
        $this->auditLogger = new NullLogger();
    }

    public function setAuditLogger(LoggerInterface $auditLogger): void
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function index(Request $request): Response
    {
        $identifier = (string) $request->query->get('identifier');
        $this->locale->setCurrentRequestLocaleFromQuery('lang');

        $payment = $this->paymentService->getPaymentPersistenceByIdentifier($identifier);

        $status = $payment->getPaymentStatus();
        if ($status !== PaymentStatus::STARTED) {
            $this->auditLogger->debug('payunity: payment not in "started" but in '.$status.', not creating a checkout!', $this->payunityService->getLoggingContext($payment));
            throw new ApiError(Response::HTTP_BAD_REQUEST, "Can't continue a payment with status: ".$status);
        }

        $contract = $payment->getPaymentContract();
        $method = $payment->getPaymentMethod();
        $contractConfig = $this->config['payment_contracts'][$contract];
        $config = $contractConfig['payment_methods_to_widgets'][$method];

        $contract = $payment->getPaymentContract();
        $amount = Tools::floatToAmount((float) $payment->getAmount());
        $currency = $payment->getCurrency();
        $paymentType = PaymentType::DEBIT;
        $extra = [];
        $testMode = $contractConfig['test_mode'];
        if ($testMode === 'internal') {
            $extra['testMode'] = 'INTERNAL';
        } elseif ($testMode === 'external') {
            $extra['testMode'] = 'EXTERNAL';
        }

        // This allows us to (manually) connect our payment entry with the transaction in the payunity web interface
        // even if the payment gets canceled or never finished.
        $extra['merchantTransactionId'] = $payment->getIdentifier();

        $checkout = $this->payunityService->prepareCheckout($payment, $contract, $amount, $currency, $paymentType, $extra);

        $loader = new FilesystemLoader(dirname(__FILE__).'/../Resources/views/');
        $twig = new Environment($loader);

        // payunity supports a list of locales, which more or less match the primary language format,
        // so just use that instead fo hardcoding the list:
        // https://www.payunity.com/tutorials/integration-guide/customisation#optionslang
        $puLocale = $this->locale->getCurrentPrimaryLanguage();

        $shopperResultUrl = Utils::extendReturnUrl($payment->getPspReturnUrl());
        $brands = $config['brands'];
        $checkoutId = $checkout->getId();
        $scriptSrc = $this->payunityService->getPaymentScriptSrc($payment, $contract, $checkoutId);
        $context = [
            'shopperResultUrl' => $shopperResultUrl,
            'brands' => $brands,
            'scriptSrc' => $scriptSrc,
            'recipient' => $payment->getRecipient(),
            'locale' => $puLocale,
        ];

        $template = $twig->load($config['template']);
        $content = $template->render($context);

        $response = new Response();
        $response->setContent($content);

        return $response;
    }
}
