<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class Connection
{
    private $apiUrl;
    private $entityId;
    private $accessToken;
    private $clientHandler;

    public function __construct($apiUrl, $entityId, $accessToken)
    {
        $this->apiUrl = $apiUrl;
        $this->entityId = $entityId;
        $this->accessToken = $accessToken;
    }

    public function setClientHandler(?object $handler): void
    {
        $this->clientHandler = $handler;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getClient(): Client
    {
        $token = $this->accessToken;

        $stack = HandlerStack::create($this->clientHandler);
        $base_uri = $this->apiUrl;
        if (substr($base_uri, -1) !== '/') {
            $base_uri .= '/';
        }

        $client_options = [
            'base_uri' => $base_uri,
            'handler' => $stack,
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ];

        $client = new Client($client_options);

        return $client;
    }
}
