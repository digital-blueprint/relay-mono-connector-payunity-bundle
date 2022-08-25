<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

class PaymentData
{
    private $code;

    private $description;

    private $id;

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function fromJsonResponse(array $jsonResponse)
    {
        if (array_key_exists('result', $jsonResponse)) {
            $result = $jsonResponse['result'];
            if (array_key_exists('code', $result)) {
                $this->code = $result['code'];
            }
            if (array_key_exists('description', $result)) {
                $this->description = $result['description'];
            }
        }
        if (array_key_exists('id', $jsonResponse)) {
            $this->id = $jsonResponse['id'];
        }
    }
}
