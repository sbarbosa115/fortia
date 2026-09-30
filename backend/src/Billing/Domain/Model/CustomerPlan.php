<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The plan an account has (PRD §6.1 CustomerPlan, embedded in the account there; its own row here, owned by
 * Billing). Validity from_at..to_at is inclusive, in calendar dates (UTC).
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_plan')]
#[ORM\Index(name: 'idx_customer_plan_subscription', columns: ['stripe_subscription_id'])]
class CustomerPlan
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $trialEnd = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $discount = null;

    /** "Already used their trial in the gateway": written once, never cleared (PRD §6.1 stripe_trial_used_at). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $trialUsedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 100)]
        private string $planId,
        #[ORM\Column(length: 10)]
        private string $fromAt,
        #[ORM\Column(length: 10)]
        private string $toAt,
        #[ORM\Column(length: 5)]
        private string $billingInterval,
        \DateTimeImmutable $at,
    ) {
        $this->createdAt = $at;
        $this->updatedAt = $at;
    }

    /** Assigns a plan for a period (sign-up, checkout, renewals, the admin). */
    public function assign(string $planId, string $fromAt, string $toAt, string $billingInterval, \DateTimeImmutable $at): void
    {
        $this->planId = $planId;
        $this->fromAt = $fromAt;
        $this->toAt = $toAt;
        $this->billingInterval = $billingInterval;
        $this->updatedAt = $at;
    }

    /** Whether $today (YYYY-MM-DD, UTC) falls inside from_at..to_at, inclusive (PRD §7.1). */
    public function isActiveOn(string $today): bool
    {
        return $this->fromAt <= $today && $today <= $this->toAt;
    }

    public function linkGateway(?string $stripeCustomerId, ?string $stripeSubscriptionId, \DateTimeImmutable $at): void
    {
        $this->stripeCustomerId = $stripeCustomerId;
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        $this->updatedAt = $at;
    }

    /** @param array<string, mixed>|null $discount */
    public function setTrialAndDiscount(?\DateTimeImmutable $trialEnd, ?array $discount, \DateTimeImmutable $at): void
    {
        $this->trialEnd = $trialEnd;
        $this->discount = $discount;
        if (null !== $trialEnd && null === $this->trialUsedAt) {
            $this->trialUsedAt = $at;
        }
        $this->updatedAt = $at;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function planId(): string
    {
        return $this->planId;
    }

    public function fromAt(): string
    {
        return $this->fromAt;
    }

    public function toAt(): string
    {
        return $this->toAt;
    }

    public function billingInterval(): string
    {
        return $this->billingInterval;
    }

    public function stripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function stripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function trialEnd(): ?\DateTimeImmutable
    {
        return $this->trialEnd;
    }

    /** @return array<string, mixed>|null */
    public function discount(): ?array
    {
        return $this->discount;
    }

    public function trialUsedAt(): ?\DateTimeImmutable
    {
        return $this->trialUsedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
