<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

class Checkout
{
    /**
     * @var ResultCode
     */
    private $result;

    /**
     * @var string
     */
    private $id;

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param mixed[] $jsonResponse
     */
    public function fromJsonResponse(array $jsonResponse): void
    {
        $res = $jsonResponse['result'];
        $this->result = new ResultCode($res['code'], $res['description']);
        $this->id = $jsonResponse['id'];
    }

    public function getResult(): ResultCode
    {
        return $this->result;
    }
}
