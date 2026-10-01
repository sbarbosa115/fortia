<?php

namespace App\Tests\Unit\Billing;

use App\Billing\Domain\PlanChange;
use App\Billing\Domain\PlanChangeKind;
use PHPUnit\Framework\TestCase;

/**
 * PRD §7.4 "Classifying a plan change": compared against the plan the gateway charges today, the first matching
 * rule applies. Prices are the plans' (monthly) price_amount in minor units; null = no price.
 */
final class PlanChangeClassificationTest extends TestCase
{
    public function testATargetWithoutAPriceIsADowngrade(): void
    {
        self::assertSame(PlanChangeKind::Downgrade, PlanChange::classify(4900, 'month', null, 'month'), 'rule 1: the target has no price');
        self::assertSame(PlanChangeKind::Downgrade, PlanChange::classify(null, 'month', null, 'year'), 'rule 1 comes before rule 3 (current without a price)');
    }

    public function testFromYearlyToMonthlyIsAlwaysADowngrade(): void
    {
        self::assertSame(PlanChangeKind::Downgrade, PlanChange::classify(4900, 'year', 14900, 'month'), 'rule 2: yearly → monthly, even to a pricier plan');
        self::assertSame(PlanChangeKind::Downgrade, PlanChange::classify(null, 'year', 14900, 'month'), 'rule 2 comes before rule 3');
    }

    public function testFromAPlanWithoutAPriceOrToAPricierPlanIsAnUpgrade(): void
    {
        self::assertSame(PlanChangeKind::Upgrade, PlanChange::classify(null, 'month', 4900, 'month'), 'rule 3: the current plan has no price');
        self::assertSame(PlanChangeKind::Upgrade, PlanChange::classify(4900, 'month', 14900, 'month'), 'rule 3: the target costs more');
        self::assertSame(PlanChangeKind::Upgrade, PlanChange::classify(4900, 'year', 14900, 'year'), 'rule 3: yearly to a pricier yearly');
        self::assertSame(PlanChangeKind::Upgrade, PlanChange::classify(4900, 'month', 14900, 'year'), 'rule 3: monthly to a pricier yearly');
    }

    public function testACheaperTargetIsADowngrade(): void
    {
        self::assertSame(PlanChangeKind::Downgrade, PlanChange::classify(14900, 'month', 4900, 'month'), 'rule 4: the target costs less');
        self::assertSame(PlanChangeKind::Downgrade, PlanChange::classify(14900, 'month', 4900, 'year'), 'rule 4: even when going yearly');
    }

    public function testSamePriceIsAnUpgradeOnlyFromMonthlyToYearly(): void
    {
        self::assertSame(PlanChangeKind::Upgrade, PlanChange::classify(4900, 'month', 4900, 'year'), 'rule 5: same price, monthly → yearly');
        self::assertSame(PlanChangeKind::Downgrade, PlanChange::classify(4900, 'month', 4900, 'month'), 'rule 5: any other same-price case');
        self::assertSame(PlanChangeKind::Downgrade, PlanChange::classify(4900, 'year', 4900, 'year'), 'rule 5: any other same-price case');
    }
}
