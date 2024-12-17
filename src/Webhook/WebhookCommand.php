<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Webhook;

use Dbp\Relay\MonoConnectorPayunityBundle\Config\ConfigurationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class WebhookCommand extends Command
{
    /**
     * @var ConfigurationService
     */
    private $config;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var PayunityWebhookService
     */
    private $webhook;

    public function __construct(
        ConfigurationService $config,
        RouterInterface $router,
        PayunityWebhookService $webhook
    ) {
        parent::__construct();

        $this->config = $config;
        $this->router = $router;
        $this->webhook = $webhook;
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('dbp:relay:mono-connector-payunity:webhook-info');
        $this->setAliases(['dbp:relay-mono-connector-payunity:webhook-info']);
        $this
            ->setDescription('Webhook info command')
            ->addArgument('contract-id', InputArgument::OPTIONAL, 'The contract ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $contractId = $input->getArgument('contract-id');
        if ($contractId === null) {
            $output->writeln("Pass one of the following contract IDs:\n");
            foreach ($this->config->getPaymentContracts() as $contract) {
                $output->writeln($contract->getIdentifier());
            }
        } else {
            // Show the user the URL which they need to use for registering a webhook
            $contract = $this->config->getPaymentContractByIdentifier($contractId);
            $webhookUrl = $this->router->generate(
                'dbp_relay_mono_connector_payunity_bundle_webhook',
                ['contract' => $contract->getIdentifier()],
                UrlGeneratorInterface::ABSOLUTE_URL);
            $output->writeln("Webhook URL for PayUnity:\n\n".$webhookUrl);

            // To allow users to test their setup, give them a curl command faking a webhook test call
            if ($contract->getWebhookSecret() === '') {
                return Command::SUCCESS;
            }
            $output->writeln('');

            $jsonPayload = '{"type": "test", "action": "webhook activation", "payload": {}}';
            $request = $this->webhook->createRequest($contract, $jsonPayload);

            $curl = [
                'curl',
                '-X', 'POST',
                '-i', '--fail',
                '-H', escapeshellarg('Content-Type: text/plain'),
                '-H', escapeshellarg('X-Initialization-Vector: '.$request->headers->get('X-Initialization-Vector')),
                '-H', escapeshellarg('X-Authentication-Tag: '.$request->headers->get('X-Authentication-Tag')),
                '-d', escapeshellarg($request->getContent()),
                escapeshellarg($webhookUrl),
            ];

            $output->writeln("CURL test command:\n");
            $output->writeln(implode(' ', $curl));
        }

        return Command::SUCCESS;
    }
}
