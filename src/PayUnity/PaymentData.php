<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

class PaymentData
{
    /**
     * @var ResultCode
     */
    private $result;

    /**
     * @var ?string
     */
    private $id;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function fromJsonResponse(array $jsonResponse)
    {
        $res = $jsonResponse['result'];
        $this->result = new ResultCode($res['code'], $res['description']);
        $this->id = $jsonResponse['id'] ?? null;
    }

    public function getResult(): ResultCode
    {
        return $this->result;
    }
}
