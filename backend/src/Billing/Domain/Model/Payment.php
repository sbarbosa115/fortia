<?php

namespace App\Billing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A paid invoice (PRD §7.4 invoice.paid "record the payment in analytics"), deduplicated by the gateway event that
 * reported it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'billing_payment')]
#[ORM\UniqueConstraint(name: 'uniq_billing_payment_event', columns: ['gateway_event_id'])]
#[ORM\Index(name: 'idx_billing_payment_customer', columns: ['customer_id', 'paid_at'])]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $gatewayEventId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $invoiceId,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $subscriptionId,
        #[ORM\Column]
        private int $amount,
        #[ORM\Column(length: 3)]
        private string $currency,
        #[ORM\Column(length: 50, nullable: true)]
        private ?string $billingReason,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $paidAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function gatewayEventId(): string
    {
        return $this->gatewayEventId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function invoiceId(): ?string
    {
        return $this->invoiceId;
    }

    public function subscriptionId(): ?string
    {
        return $this->subscriptionId;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function billingReason(): ?string
    {
        return $this->billingReason;
    }

    public function paidAt(): \DateTimeImmutable
    {
        return $this->paidAt;
    }
}
