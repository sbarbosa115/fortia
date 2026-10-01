<?php

namespace App\Billing\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12, from invoice.payment_failed. Payload: {subscription_id, invoice_id, amount, currency}. */
final class PaymentFailed extends BaseDomainEvent
{
}
