<?php

namespace App\Tests\Unit\Billing;

use App\Billing\Domain\Model\CustomerPlan;
use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\PlanRules;
use PHPUnit\Framework\TestCase;

final class PlanRulesTest extends TestCase
{
    private const TODAY = '2026-09-30';

    public function testAnAccountWithoutAPlanIsRejected(): void
    {
        self::assertSame('NO_PLAN', PlanRules::capacity(null, null, self::TODAY, 'regular', [])?->reason);
    }

    public function testAPlanOutsideItsWindowIsInactive(): void
    {
        $customerPlan = $this->customerPlan('2026-08-01', '2026-08-31');

        self::assertSame('PLAN_INACTIVE', PlanRules::capacity($customerPlan, $this->plan(), self::TODAY, 'regular', [])?->reason);
    }

    public function testTheWindowIsInclusiveOnBothEnds(): void
    {
        $plan = $this->plan(['regular' => 5]);

        self::assertNull(PlanRules::capacity($this->customerPlan('2026-09-30', '2026-10-30'), $plan, self::TODAY, 'regular', []), 'from_at itself is inside');
        self::assertNull(PlanRules::capacity($this->customerPlan('2026-08-30', '2026-09-30'), $plan, self::TODAY, 'regular', []), 'to_at itself is inside');
    }

    public function testAPlanMissingFromTheCatalogIsNotFound(): void
    {
        self::assertSame('PLAN_NOT_FOUND', PlanRules::capacity($this->customerPlan(), null, self::TODAY, 'regular', [])?->reason);
    }

    public function testAFeatureTheActivePlanDoesNotListIsNotInPlan(): void
    {
        self::assertSame('FEATURE_NOT_IN_PLAN', PlanRules::capacity($this->customerPlan(), $this->plan(['regular' => 5]), self::TODAY, 'chat', [])?->reason);
    }

    public function testALimitOfZeroMeansNotIncluded(): void
    {
        self::assertSame('FEATURE_NOT_IN_PLAN', PlanRules::capacity($this->customerPlan(), $this->plan(['chat' => 0]), self::TODAY, 'chat', [])?->reason, 'PRD §6.4: 0 = not included');
    }

    public function testANegativeLimitIsUnlimited(): void
    {
        self::assertNull(PlanRules::capacity($this->customerPlan(), $this->plan(['chat' => -1]), self::TODAY, 'chat', ['chat' => 999999]));
    }

    public function testRejectedWhenUsedReachesTheLimit(): void
    {
        $plan = $this->plan(['users' => 3]);

        self::assertNull(PlanRules::capacity($this->customerPlan(), $plan, self::TODAY, 'users', ['users' => 2]));
        self::assertSame('FEATURE_LIMIT_REACHED', PlanRules::capacity($this->customerPlan(), $plan, self::TODAY, 'users', ['users' => 3])?->reason, 'PRD §7.1: rejected when used >= limit');
    }

    public function testMaxQuestionnairesLimitsTheSumOfTheFiveQuestionnaireCounters(): void
    {
        $plan = $this->plan(['regular' => -1, 'diagnostic' => -1, 'chat' => -1], maxQuestionnaires: 5);
        $usage = ['regular' => 2, 'diagnostic' => 2, 'chat' => 1, 'users' => 50];

        self::assertSame('QUESTIONNAIRE_LIMIT_REACHED', PlanRules::capacity($this->customerPlan(), $plan, self::TODAY, 'regular', $usage)?->reason);
        self::assertNull(PlanRules::capacity($this->customerPlan(), $plan, self::TODAY, 'regular', ['regular' => 4]));
    }

    public function testResponsesAreLimitedByMaxResponsesNotTheFeatureList(): void
    {
        $plan = $this->plan([], maxResponses: 10);

        self::assertNull(PlanRules::capacity($this->customerPlan(), $plan, self::TODAY, 'responses', ['responses' => 9]), 'responses need no feature row');
        self::assertSame('RESPONSE_LIMIT_REACHED', PlanRules::capacity($this->customerPlan(), $plan, self::TODAY, 'responses', ['responses' => 10])?->reason);
    }

    public function testNullOrNegativeCapsAreUnlimited(): void
    {
        self::assertNull(PlanRules::capacity($this->customerPlan(), $this->plan([], maxResponses: null), self::TODAY, 'responses', ['responses' => 10 ** 6]));
        self::assertNull(PlanRules::capacity($this->customerPlan(), $this->plan([], maxResponses: -1), self::TODAY, 'responses', ['responses' => 10 ** 6]));
    }

    public function testTheFeatureGateOnlyChecksThatThePlanIncludesTheFeature(): void
    {
        $plan = $this->plan(['api' => 1]);

        self::assertNull(PlanRules::feature($this->customerPlan(), $plan, self::TODAY, 'api'), 'an exhausted quota does not block a feature gate');
        self::assertSame('FEATURE_NOT_IN_PLAN', PlanRules::feature($this->customerPlan(), $plan, self::TODAY, 'webhook')?->reason);
        self::assertSame('PLAN_INACTIVE', PlanRules::feature($this->customerPlan('2020-01-01', '2020-01-31'), $plan, self::TODAY, 'api')?->reason);
    }

    private function customerPlan(string $from = '2026-09-15', string $to = '2026-10-15'): CustomerPlan
    {
        return new CustomerPlan('cust0001', 'pro', $from, $to, 'month', new \DateTimeImmutable());
    }

    /** @param array<string, int> $limits */
    private function plan(array $limits = [], ?int $maxQuestionnaires = null, ?int $maxResponses = null): Plan
    {
        $features = [];
        foreach ($limits as $feature => $limit) {
            $features[] = ['feature_id' => $feature, 'limit' => $limit];
        }

        return new Plan('pro', ['plan_name' => 'Pro', 'features' => $features, 'max_questionnaires' => $maxQuestionnaires, 'max_responses' => $maxResponses], new \DateTimeImmutable());
    }
}
