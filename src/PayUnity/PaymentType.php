<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\PayUnity;

/**
 * The payment type for the request.
 * See https://www.payunity.com/reference/parameters.
 */
class PaymentType
{
    /**
     * Preauthorization: A stand-alone authorisation that will also trigger optional risk management and validation.
     * A Capture (CP) with reference to the Preauthorisation (PA) will confirm the payment..
     */
    public const PA = 'PA';

    /**
     * Debit: Debits the account of the end customer and credits the merchant account.
     */
    public const DB = 'DB';

    /**
     * Credit: Credits the account of the end customer and debits the merchant account.
     */
    public const CD = 'CD';

    /**
     * Capture: Captures a preauthorized (PA) amount.
     */
    public const CP = 'CP';

    /**
     * Reversal: Reverses an already processed Preauthorization (PA), Debit (DB) or Credit (CD) transaction.
     * As a consequence, the end customer will never see any booking on his statement. A Reversal is only possible
     * until a connector specific cut-off time. Some connectors don't support Reversals.
     */
    public const RV = 'RV';

    /**
     * Refund: Credits the account of the end customer with a reference to a prior Debit (DB) or Credit (CD)
     * transaction. The end customer will always see two bookings on his statement.
     * Some connectors do not support Refunds.
     */
    public const RF = 'RF';
}
