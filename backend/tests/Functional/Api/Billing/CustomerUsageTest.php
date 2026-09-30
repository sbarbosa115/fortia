<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Billing;

use App\Billing\Application\PlanGate;
use App\Billing\Application\PlanLimitReached;
use App\Identity\Domain\Event\UserSignedIn;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Event\BaseDomainEvent;
use App\Tests\Support\ApiTestCase;

final class CustomerUsageTest extends ApiTestCase
{
    public function testShowsThePlanTheUsageAndAVerdictPerFeature(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');

        $usage = $this->data($this->api('GET', '/api/v1/customer/usage', as: $email));

        self::assertTrue($usage['plan_active']);
        self::assertSame('starter', $usage['plan']['id']);
        self::assertArrayNotHasKey('features', $usage['plan'], 'PRD §8.3: the plan without its features');
        self::assertSame(0, $usage['usage']['questionnaires_used']);
        self::assertSame(['allowed' => false, 'reason' => 'FEATURE_NOT_IN_PLAN', 'limit' => null, 'used' => 0], $usage['features']['api'], 'starter has no API');
        self::assertSame(100, $usage['features']['responses']['limit'], 'responses are limited by max_responses');
        self::assertCount(15, $usage['features'], 'every feature of the catalog');
    }

    public function testAnEventWithAFeatureCountsOneUnitAndTheGateStopsAtTheLimit(): void
    {
        $this->account('GLOBEX01', plan: 'starter');
        $events = static::getContainer()->get(EventBus::class);
        $gate = static::getContainer()->get(PlanGate::class);

        $events->publish(new CountedForTest('GLOBEX01', 'users'));
        $gate->capacityForAccount('GLOBEX01', 'users');
        $events->publish(new CountedForTest('GLOBEX01', 'users'));

        $this->expectException(PlanLimitReached::class);
        $gate->capacityForAccount('GLOBEX01', 'users');
    }

    public function testAnEventWithoutAFeatureCountsNothing(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        static::getContainer()->get(EventBus::class)->publish(new UserSignedIn('GLOBEX01', null, []));

        $usage = $this->data($this->api('GET', '/api/v1/customer/usage', as: $email));

        self::assertSame([], array_filter(array_column($usage['features'], 'used')));
    }

    public function testAnAccountWithoutAPlanIsInactive(): void
    {
        $email = $this->account('NOPLAN01', plan: null);

        $usage = $this->data($this->api('GET', '/api/v1/customer/usage', as: $email));

        self::assertFalse($usage['plan_active']);
        self::assertNull($usage['customer_plan']);
        self::assertSame('NO_PLAN', $usage['features']['regular']['reason']);
    }
}

final class CountedForTest extends BaseDomainEvent
{
}
