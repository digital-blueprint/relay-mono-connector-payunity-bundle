<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class Connection implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $apiUrl;
    private $entityId;
    private $accessToken;
    private $clientHandler;

    public function __construct($apiUrl, $entityId, $accessToken)
    {
        $this->apiUrl = $apiUrl;
        $this->entityId = $entityId;
        $this->accessToken = $accessToken;
        $this->logger = new NullLogger();
    }

    public function setClientHandler(?object $handler): void
    {
        $this->clientHandler = $handler;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getBaseUri(): string
    {
        $base_uri = $this->apiUrl;
        if (substr($base_uri, -1) !== '/') {
            $base_uri .= '/';
        }

        return $base_uri;
    }

    public function getClient(): Client
    {
        $token = $this->accessToken;

        $stack = HandlerStack::create($this->clientHandler);

        $client_options = [
            'base_uri' => $this->getBaseUri(),
            'handler' => $stack,
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ];

        if ($this->logger !== null) {
            $stack->push(Tools::createLoggerMiddleware($this->logger));
        }

        $client = new Client($client_options);

        return $client;
    }
}
