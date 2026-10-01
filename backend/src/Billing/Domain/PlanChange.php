<?php

namespace App\Billing\Domain;

/**
 * Classifying a plan change (PRD §7.4), compared against the plan the gateway is actually charging today. Prices
 * are the plans' price_amount (minor units; null = no price); intervals are "month" or "year". The first matching
 * rule applies:
 *
 * 1. The target has no price → downgrade.
 * 2. From yearly to monthly → always downgrade.
 * 3. The current plan has no price, or the target costs more → upgrade.
 * 4. The target costs less → downgrade.
 * 5. Same price, from monthly to yearly → upgrade; any other same-price case → downgrade.
 */
final class PlanChange
{
    public static function classify(?int $currentPrice, string $currentInterval, ?int $targetPrice, string $targetInterval): PlanChangeKind
    {
        if (null === $targetPrice) {
            return PlanChangeKind::Downgrade;
        }
        if ('year' === $currentInterval && 'month' === $targetInterval) {
            return PlanChangeKind::Downgrade;
        }
        if (null === $currentPrice || $targetPrice > $currentPrice) {
            return PlanChangeKind::Upgrade;
        }
        if ($targetPrice < $currentPrice) {
            return PlanChangeKind::Downgrade;
        }

        return 'month' === $currentInterval && 'year' === $targetInterval ? PlanChangeKind::Upgrade : PlanChangeKind::Downgrade;
    }
}
