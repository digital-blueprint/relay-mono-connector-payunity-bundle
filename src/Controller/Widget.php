<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Controller;

use Dbp\Relay\MonoBundle\Service\PaymentService;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\PaymentDataService;
use Dbp\Relay\MonoConnectorPayunityBundle\Service\PayunityFlexService;
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
     * @var PayunityFlexService
     */
    private $payunityFlexService;

    /**
     * @var PaymentDataService
     */
    private $paymentDataService;

    public function __construct(
        PaymentService $paymentService,
        PayunityFlexService $payunityFlexService,
        PaymentDataService $paymentDataService
    ) {
        $this->paymentService = $paymentService;
        $this->payunityFlexService = $payunityFlexService;
        $this->paymentDataService = $paymentDataService;
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
        if ($request->query->has('lang')) {
            $lang = $request->query->get('lang');
            assert(is_string($lang));
            $locale = \Locale::acceptFromHttp($lang);
            if ($locale !== false) {
                $request->setLocale($locale);
            }
        }

        $payment = $this->paymentService->getPaymentPersistenceByIdentifier($identifier);

        $contract = $payment->getPaymentContract();
        $method = $payment->getPaymentMethod();
        $contractConfig = $this->config['payment_contracts'][$contract];
        $config = $contractConfig['payment_methods_to_widgets'][$method];

        $body = [
            'amount' => number_format((float) $payment->getAmount(), 2, '.', ''),
            'currency' => $payment->getCurrency(),
            'paymentType' => 'DB',
        ];

        $contract = $payment->getPaymentContract();
        $paymentData = $this->payunityFlexService->postPaymentData($contract, $body);
        $this->paymentDataService->createPaymentData($payment, $paymentData);

        $loader = new FilesystemLoader(dirname(__FILE__).'/../Resources/views/');
        $twig = new Environment($loader);

        // payunity supports a list of locales, which more or less match the primary language format,
        // so just use that instead fo hardcoding the list:
        // https://www.payunity.com/tutorials/integration-guide/customisation#optionslang
        $puLocale = \Locale::getPrimaryLanguage($request->getLocale()) ?? 'en';

        $shopperResultUrl = $payment->getPspReturnUrl();
        $brands = $config['brands'];
        $checkoutId = $paymentData->getId();
        $scriptSrc = $contractConfig['api_url'].'/v1/paymentWidgets.js?checkoutId='.$checkoutId;
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
