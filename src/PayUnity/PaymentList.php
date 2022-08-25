<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

class PaymentList
{
    /**
     * @var ResultCode
     */
    private $result;

    /**
     * @var PaymentData[]
     */
    private $payments;

    /**
     * @return PaymentData[]
     */
    public function getPayments(): array
    {
        return $this->payments;
    }

    public function fromJsonResponse(array $jsonResponse)
    {
        $res = $jsonResponse['result'];
        $this->result = new ResultCode($res['code'], $res['description']);

        $payments = [];
        foreach ($jsonResponse['payments'] as $item) {
            $payment = new PaymentData();
            $payment->fromJsonResponse($item);
            $payments[] = $payment;
        }

        $this->payments = $payments;
    }

    public function getResult(): ResultCode
    {
        return $this->result;
    }
}
