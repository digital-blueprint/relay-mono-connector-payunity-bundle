<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Persistence;

use Dbp\Relay\MonoBundle\Persistence\PaymentPersistence;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Checkout;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="mono_connector_payunity_payments", indexes={
 *     @ORM\Index(name="payment_identifier_idx", fields={"paymentIdentifier"}),
 *     @ORM\Index(name="psp_identifier_idx", fields={"pspIdentifier"}),
 * })
 */
class PaymentDataPersistence
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $identifier;

    /**
     * @var \DateTimeInterface
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $paymentIdentifier;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $pspIdentifier;

    public function getIdentifier(): int
    {
        return $this->identifier;
    }

    public function setIdentifier(int $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPaymentIdentifier(): string
    {
        return $this->paymentIdentifier;
    }

    public function setPaymentIdentifier(string $paymentIdentifier): self
    {
        $this->paymentIdentifier = $paymentIdentifier;

        return $this;
    }

    public function getPspIdentifier(): string
    {
        return $this->pspIdentifier;
    }

    public function setPspIdentifier(string $pspIdentifier): self
    {
        $this->pspIdentifier = $pspIdentifier;

        return $this;
    }

    public static function fromPaymentAndCheckout(PaymentPersistence $payment, Checkout $checkout): PaymentDataPersistence
    {
        $paymentDataPersistence = new PaymentDataPersistence();
        $paymentDataPersistence->setPaymentIdentifier($payment->getIdentifier());
        $paymentDataPersistence->setPspIdentifier($checkout->getId());

        return $paymentDataPersistence;
    }
}
