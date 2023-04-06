<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

class WebhookRequest
{
    /**
     * This type of notification is sent when a payment is created or updated in the system.
     */
    public const TYPE_PAYMENT = 'PAYMENT';
    /**
     * This type of notification is sent when a registration is created or deleted.
     */
    public const TYPE_REGISTRATION = 'REGISTRATION';
    /**
     * This type of notification is sent when a risk transaction is created or deleted.
     */
    public const TYPE_RISK = 'RISK';

    /**
     * This gets sent as a test to see if webhooks are working. It doesn't seem
     * to be documented.
     */
    public const TYPE_TEST = 'test';

    /**
     * when registration has been created.
     */
    public const ACTION_CREATED = 'CREATED';
    /**
     * when registration has been updated.
     */
    public const ACTION_UPDATED = 'UPDATED';
    /**
     * when registration has been deleted.
     */
    public const ACTION_DELETED = 'DELETED';

    /**
     * Type of the notification.
     *
     * @var string
     */
    private $type;

    /**
     * Indicator of status change. This field is available only if the type is REGISTRATION.
     *
     * @var ?string
     */
    private $action;

    /**
     * Content of the notification.
     * If the notification type is payment or registration, the payload's content will be identical
     * to the response you received on the payment or registration.
     *
     * @var array
     */
    private $payload;

    public function __construct(string $type, ?string $action, array $payload)
    {
        $this->type = $type;
        $this->action = $action;
        $this->payload = $payload;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
