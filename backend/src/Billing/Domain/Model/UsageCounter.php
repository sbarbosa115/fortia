<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * How much of one feature an account used in one plan period (PRD §7.1 "counters are per monthly period", §13.8
 * absorbed into the API). The period is the plan window it was counted in.
 */
#[ORM\Entity]
#[ORM\Table(name: 'usage_counter')]
#[ORM\UniqueConstraint(name: 'uniq_usage_period_feature', columns: ['customer_id', 'period_from', 'feature'])]
class UsageCounter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $used = 0;

    public function __construct(
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 10)]
        private string $periodFrom,
        #[ORM\Column(length: 10)]
        private string $periodTo,
        #[ORM\Column(length: 100)]
        private string $feature,
    ) {
    }

    public function add(int $amount = 1): void
    {
        $this->used += $amount;
    }

    public function set(int $used): void
    {
        $this->used = max(0, $used);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function periodFrom(): string
    {
        return $this->periodFrom;
    }

    public function periodTo(): string
    {
        return $this->periodTo;
    }

    public function feature(): string
    {
        return $this->feature;
    }

    public function used(): int
    {
        return $this->used;
    }
}
