<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'mono_connector_payunity_payments')]
#[ORM\Index(name: 'payment_identifier_idx', fields: ['paymentIdentifier'])]
#[ORM\Index(name: 'psp_identifier_idx', fields: ['pspIdentifier'])]
#[ORM\Entity]
class PaymentDataPersistence
{
    /**
     * @var int
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $identifier;

    /**
     * @var \DateTimeInterface
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private $createdAt;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string')]
    private $paymentIdentifier;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string')]
    private $pspIdentifier;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', nullable: true)]
    private $pspContract;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', nullable: true)]
    private $pspMethod;

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

    public function getPspContract(): ?string
    {
        return $this->pspContract;
    }

    public function setPspContract(string $pspContract): void
    {
        $this->pspContract = $pspContract;
    }

    public function getPspMethod(): ?string
    {
        return $this->pspMethod;
    }

    public function setPspMethod(string $pspMethod): void
    {
        $this->pspMethod = $pspMethod;
    }
}
