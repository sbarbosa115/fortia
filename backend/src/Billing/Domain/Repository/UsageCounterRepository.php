<?php

declare(strict_types=1);

namespace App\Billing\Domain\Repository;

use App\Billing\Domain\Model\UsageCounter;

interface UsageCounterRepository
{
    /**
     * Used units per feature in the period that starts on $periodFrom.
     *
     * @return array<string, int>
     */
    public function usage(string $customerId, string $periodFrom): array;

    /** The counter of one feature in one period, created at 0 when missing. */
    public function counter(string $customerId, string $periodFrom, string $periodTo, string $feature): UsageCounter;

    /** Sets every counter of the period to 0 (an upgrade to a more expensive plan, PRD §7.4). */
    public function resetPeriod(string $customerId, string $periodFrom): void;
}
