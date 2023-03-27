<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

class WebhookRequest
{
    public const TYPE_PAYMENT = 'PAYMENT';
    public const TYPE_REGISTRATION = 'REGISTRATION';
    public const TYPE_RISK = 'RISK';

    public const ACTION_CREATED = 'CREATED';
    public const ACTION_UPDATED = 'UPDATED';
    public const ACTION_DELETED = 'DELETED';

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $action;

    /**
     * @var array
     */
    private $payload;

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }
}
