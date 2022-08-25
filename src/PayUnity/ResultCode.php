<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

/**
 * https://payunity.docs.oppwa.com/reference/resultCodes.
 */
class ResultCode
{
    /**
     * @var string
     */
    private $code;
    /**
     * @var string
     */
    private $description;

    public function __construct(string $code, string $description = '')
    {
        $this->code = $code;
        $this->description = $description;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    private function matches(string $pattern)
    {
        $res = preg_match($pattern, $this->code);
        assert($res !== false);

        return $res === 1;
    }

    /**
     * Result codes for successfully processed transactions.
     */
    public function isSuccessfullyProcessed(): bool
    {
        return $this->matches('/^(000\.000\.|000\.100\.1|000\.[36])/');
    }

    /**
     * Result codes for successfully processed transactions that should be manually reviewed.
     */
    public function isSuccessfullyProcessedNeedsManualReview(): bool
    {
        return $this->matches('/^(000\.400\.0[^3]|000\.400\.[0-1]{2}0)/');
    }

    /**
     * Result codes for pending transactions.
     * These codes mean that there is an open session in the background, meaning within half an hour there will
     * be a status change, if nothing else happens, to timeout.
     */
    public function isPending(): bool
    {
        return $this->matches('/^(000\.200)/');
    }

    /**
     * These codes describe a situation where the status of a transaction can change even after several days.
     */
    public function isPendingExtra(): bool
    {
        return $this->matches('/^(800\.400\.5|100\.400\.500)/');
    }
}
