<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Config;

class PaymentMethod
{
    /**
     * @var string
     */
    private $identifier;

    /**
     * @var string[]
     */
    private array $brands;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @return string[]
     */
    public function getBrands(): array
    {
        return $this->brands;
    }

    /**
     * @param string[] $brands
     */
    public function setBrands(array $brands): void
    {
        $this->brands = $brands;
    }
}
