<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

class HealthCheck implements CheckInterface
{
    /**
     * @var PayunityService
     */
    private $payunity;
    /**
     * @var PaymentDataService
     */
    private $dataService;
    /**
     * @var ConfigurationService
     */
    private $config;

    public function __construct(PayunityService $payunity, PaymentDataService $dataService, ConfigurationService $config)
    {
        $this->payunity = $payunity;
        $this->dataService = $dataService;
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'mono-connector-payunity';
    }

    private function checkMethod(string $description, callable $func, array $args = []): CheckResult
    {
        $result = new CheckResult($description);
        try {
            $func(...$args);
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);

            return $result;
        }
        $result->set(CheckResult::STATUS_SUCCESS);

        return $result;
    }

    public function check(CheckOptions $options): array
    {
        $results = [];
        foreach ($this->payunity->getContracts() as $contract) {
            $results[] = $this->checkMethod('Check if we can connect to the PayUnity API ('.$contract.')', [$this->payunity, 'checkConnection'], [$contract]);
        }

        $results[] = $this->checkMethod('Check if we can connect to the DB', [$this->dataService, 'checkConnection']);

        $results[] = $this->checkMethod('Check contract config', [$this->config, 'checkConfig']);

        return $results;
    }
}
