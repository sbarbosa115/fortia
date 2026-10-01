<?php

namespace App\Tests\Functional\Api\Billing;

use App\Billing\Application\Usage;
use App\Billing\Domain\Model\CustomerPlan;
use App\Billing\Infrastructure\Gateway\Fake\FakePaymentGateway;
use App\Tests\Support\ApiTestCase;

/** PRD §7.3–7.4, §8.3: plans, checkout, plan changes, cancel/resume, the portal — on the fake gateway. */
final class CheckoutTest extends ApiTestCase
{
    public function testThePlansListPricedPlansFirstByPriceThenTheRestByName(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');

        $plans = $this->data($this->api('GET', '/api/v1/plans', as: $email));

        self::assertSame(['pro', 'business', 'enterprise', 'starter'], array_column($plans['plans'], 'id'), 'PRD §8.3: priced plans first (by price), then by name');
        self::assertSame('starter', $plans['current_plan_id']);
        self::assertSame('month', $plans['current_billing_interval']);
        self::assertSame($this->clock()->today() < $plans['active_until'], true, 'active_until is the end of the plan window');
        self::assertNull($plans['scheduled_plan_id']);
        self::assertFalse($plans['cancel_at_period_end']);
        self::assertTrue($plans['trial_eligible'], 'PRD §7.3: the trial is once per account');
        self::assertNull($plans['discount']);
        $pro = $plans['plans'][0];
        self::assertTrue($pro['purchasable']);
        self::assertTrue($pro['yearly_purchasable']);
        self::assertSame(14, $pro['trial_days']);
        self::assertContains(['feature_id' => 'regular', 'feature_name' => 'Regular questionnaires', 'limit' => -1], $pro['features']);
        self::assertFalse($plans['plans'][2]['purchasable'], 'enterprise has no price: "Get in touch"');
    }

    public function testACheckoutIsAHostedPageWithTheMetadataAndTheReturnUrls(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');

        $url = $this->data($this->api('POST', '/api/v1/checkout/session', ['plan_id' => 'pro', 'billing_interval' => 'year'], as: $email))['checkout_url'];

        self::assertMatchesRegularExpression('#/fake-gateway/checkout/cs_fake_\w+$#', $url);
        $session = $this->gateway()->checkoutSession(basename($url));
        self::assertNotNull($session);
        self::assertSame(47000, $session['amount'], 'the yearly price');
        self::assertSame('year', $session['interval']);
        self::assertSame(14, $session['trial_days'], 'PRD §7.3: a trial when the plan has trial days and the account never used one');
        self::assertStringEndsWith('/profile/plans?checkout=success', $session['success_url'], 'PRD §7.4 return URLs');
        self::assertStringEndsWith('/profile/plans?checkout=cancel', $session['cancel_url']);
    }

    public function testTheBillingIntervalDefaultsToMonthly(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');

        $url = $this->data($this->api('POST', '/api/v1/checkout/session', ['plan_id' => 'business'], as: $email))['checkout_url'];

        self::assertSame(14900, $this->gateway()->checkoutSession(basename($url))['amount'] ?? null);
    }

    public function testACheckoutNeedsAPlanThatExistsAndCanBeBought(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');

        $this->assertApiError($this->api('POST', '/api/v1/checkout/session', ['plan_id' => 'nope'], as: $email), 404, 'PLAN_NOT_FOUND');
        $this->assertApiError($this->api('POST', '/api/v1/checkout/session', ['plan_id' => 'enterprise'], as: $email), 400, 'PLAN_NOT_PURCHASABLE', 'no price: price on request');
        $this->assertApiError($this->api('POST', '/api/v1/checkout/session', ['plan_id' => 'pro', 'billing_interval' => 'week'], as: $email), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('POST', '/api/v1/checkout/session', ['plan_id' => 'pro'], as: null), 401, 'UNAUTHORIZED');
    }

    public function testAReadOnlyMemberCannotChangeBilling(): void
    {
        $this->account('ACME0001', plan: 'starter');
        $this->user('ACME0001', 'reader@acme0001.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('POST', '/api/v1/checkout/session', ['plan_id' => 'pro'], as: 'reader@acme0001.test'), 403, 'FORBIDDEN', 'PRD §4.2: editing billing needs write permission');
        self::assertSame(200, $this->api('GET', '/api/v1/plans', as: 'reader@acme0001.test')['status'], 'but they can see the plans');
    }

    public function testPayingTheCheckoutAssignsThePlanAndUsesTheTrial(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');

        $this->subscribe($email, 'pro');

        $plans = $this->data($this->api('GET', '/api/v1/plans', as: $email));
        self::assertSame('pro', $plans['current_plan_id'], 'checkout.session.completed assigns the plan');
        self::assertFalse($plans['trial_eligible'], 'PRD §7.3: the first trial marks the account');
        self::assertNotNull($plans['trial_end']);
        $customerPlan = $this->customerPlan('GLOBEX01');
        self::assertNotNull($customerPlan->stripeSubscriptionId());
        self::assertNotNull($customerPlan->stripeCustomerId());
        self::assertNotNull($customerPlan->trialUsedAt());
        self::assertSame($this->clock()->today(), $customerPlan->fromAt(), 'the period of the subscription');
        self::assertSame(1, $this->eventCount('GLOBEX01', 'SubscriptionCreated'));
    }

    public function testASecondCheckoutGetsNoTrialAndReusesTheGatewayCustomer(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'pro');
        $customer = $this->customerPlan('GLOBEX01')->stripeCustomerId();
        $this->webhook('customer.subscription.deleted', ['id' => $this->customerPlan('GLOBEX01')->stripeSubscriptionId(), 'object' => 'subscription', 'customer' => $customer, 'metadata' => []]);

        $url = $this->data($this->api('POST', '/api/v1/checkout/session', ['plan_id' => 'pro'], as: $email))['checkout_url'];

        self::assertSame(0, $this->gateway()->checkoutSession(basename($url))['trial_days'] ?? null, 'PRD §7.3: only once per account');
        $this->pay($url);
        self::assertSame($customer, $this->customerPlan('GLOBEX01')->stripeCustomerId(), 'PRD §7.4: reuses the gateway customer');
    }

    public function testCancellingTheHostedPageChangesNothing(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $url = $this->data($this->api('POST', '/api/v1/checkout/session', ['plan_id' => 'pro'], as: $email))['checkout_url'];

        $this->client->request('POST', parse_url($url, \PHP_URL_PATH).'/cancel');

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertStringEndsWith('/profile/plans?checkout=cancel', (string) $this->client->getResponse()->headers->get('Location'));
        self::assertSame('starter', $this->data($this->api('GET', '/api/v1/plans', as: $email))['current_plan_id']);
    }

    public function testAPlanChangeWithoutASubscriptionStartsACheckout(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');

        $change = $this->data($this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'pro'], as: $email));

        self::assertSame('checkout', $change['type']);
        self::assertSame('pro', $change['plan_id']);
        self::assertSame('month', $change['billing_interval']);
        self::assertStringContainsString('/fake-gateway/checkout/', $change['checkout_url']);
    }

    public function testAnUpgradeIsImmediateAndResetsTheUsage(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'pro');
        static::getContainer()->get(Usage::class)->record('GLOBEX01', 'regular', 3);
        $this->em()->flush();

        $change = $this->data($this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'business', 'billing_interval' => 'month'], as: $email));

        self::assertSame(['type' => 'changed', 'plan_id' => 'business', 'billing_interval' => 'month', 'change' => 'upgrade'], array_intersect_key($change, array_flip(['type', 'plan_id', 'billing_interval', 'change'])));
        self::assertNotNull($change['effective_at']);
        self::assertSame('business', $this->data($this->api('GET', '/api/v1/plans', as: $email))['current_plan_id'], 'PRD §7.4: the price changes right away');
        $usage = $this->data($this->api('GET', '/api/v1/customer/usage', as: $email));
        self::assertSame(0, $usage['features']['regular']['used'], 'PRD §7.4: a more expensive plan resets every counter');
        self::assertSame(1, $this->eventCount('GLOBEX01', 'PlanChanged'));
    }

    public function testAnUpgradeWhoseChargeFailsChangesNothing(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'pro');
        $this->gateway()->declineCards((string) $this->customerPlan('GLOBEX01')->stripeCustomerId());

        $this->assertApiError($this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'business'], as: $email), 502, 'STRIPE_UNAVAILABLE', 'PRD §7.4: if that charge fails, the change fails');
        self::assertSame('pro', $this->data($this->api('GET', '/api/v1/plans', as: $email))['current_plan_id']);
    }

    public function testADowngradeIsScheduledForTheEndOfThePeriodAndCanBeReverted(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'business');

        $change = $this->data($this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'pro'], as: $email));

        self::assertSame('downgrade', $change['change']);
        $plans = $this->data($this->api('GET', '/api/v1/plans', as: $email));
        self::assertSame('business', $plans['current_plan_id'], 'PRD §7.4: a downgrade waits for the next cycle');
        self::assertSame('pro', $plans['scheduled_plan_id']);
        self::assertSame('month', $plans['scheduled_billing_interval']);
        self::assertSame(substr((string) $change['effective_at'], 0, 10), $plans['active_until'], 'effective at the end of the period');

        $revert = $this->data($this->api('POST', '/api/v1/checkout/plan-change/revert', as: $email));

        self::assertSame('business', $revert['plan_id']);
        self::assertNotNull($revert['renews_at']);
        self::assertNull($this->data($this->api('GET', '/api/v1/plans', as: $email))['scheduled_plan_id']);
        $this->assertApiError($this->api('POST', '/api/v1/checkout/plan-change/revert', as: $email), 400, 'NO_SCHEDULED_CHANGE');
    }

    public function testAtRenewalTheScheduledPlanStarts(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'business');
        $this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'pro'], as: $email);
        $subscription = (string) $this->customerPlan('GLOBEX01')->stripeSubscriptionId();

        $this->gateway()->renew($subscription);
        $this->api('GET', '/api/v1/plans', as: $email); // any request delivers the fake gateway's webhooks

        $plans = $this->data($this->api('GET', '/api/v1/plans', as: $email));
        self::assertSame('pro', $plans['current_plan_id'], 'PRD §7.4: then the schedule is released and it renews on the new plan');
        self::assertNull($plans['scheduled_plan_id']);
        self::assertSame(1, $this->eventCount('GLOBEX01', 'SubscriptionRenewed'));
        self::assertTrue($this->data($this->api('GET', '/api/v1/customer/usage', as: $email))['plan_active'], 'the renewed period is the plan window: the plan stays active (PRD §7.1)');
    }

    public function testACancelledSubscriptionEndsAtRenewalAndKeepsThePaidPlan(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'pro');
        $this->api('POST', '/api/v1/checkout/cancel', as: $email);

        $this->gateway()->renew((string) $this->customerPlan('GLOBEX01')->stripeSubscriptionId());
        $this->api('GET', '/api/v1/plans', as: $email);

        $plan = $this->customerPlan('GLOBEX01');
        self::assertNull($plan->stripeSubscriptionId(), 'PRD §7.4 customer.subscription.deleted: the subscription id is forgotten');
        self::assertSame('pro', $plan->planId(), 'the paid window expires on its own');
        self::assertSame(1, $this->eventCount('GLOBEX01', 'SubscriptionCancelled'));
        $this->assertApiError($this->api('POST', '/api/v1/checkout/resume', as: $email), 400, 'NO_SUBSCRIPTION');
    }

    public function testChoosingThePlanYouHaveIsRefused(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'pro');

        $this->assertApiError($this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'pro', 'billing_interval' => 'month'], as: $email), 400, 'SAME_PLAN');
        $this->assertApiError($this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'enterprise'], as: $email), 400, 'PLAN_NOT_PURCHASABLE');
        $this->assertApiError($this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'ghost'], as: $email), 404, 'PLAN_NOT_FOUND');
    }

    public function testGoingFromYearlyBackToMonthlyIsADowngrade(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'pro', 'year');

        $change = $this->data($this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'pro', 'billing_interval' => 'month'], as: $email));

        self::assertSame('downgrade', $change['change'], 'PRD §7.4 rule 2');
        self::assertSame('year', $this->data($this->api('GET', '/api/v1/plans', as: $email))['current_billing_interval']);
    }

    public function testCancelKeepsThePaidWindowAndResumeUndoesIt(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'business');
        $this->api('POST', '/api/v1/checkout/plan-change', ['plan_id' => 'pro'], as: $email);

        $cancel = $this->data($this->api('POST', '/api/v1/checkout/cancel', as: $email));

        self::assertSame('business', $cancel['plan_id']);
        self::assertNotNull($cancel['active_until']);
        $plans = $this->data($this->api('GET', '/api/v1/plans', as: $email));
        self::assertTrue($plans['cancel_at_period_end'], 'PRD §7.4: cancel = cancel at period end');
        self::assertNull($plans['scheduled_plan_id'], 'cancellation first releases any pending schedule');
        self::assertSame('business', $plans['current_plan_id']);

        $resume = $this->data($this->api('POST', '/api/v1/checkout/resume', as: $email));
        self::assertSame('business', $resume['plan_id']);
        self::assertNotNull($resume['renews_at']);
        self::assertFalse($this->data($this->api('GET', '/api/v1/plans', as: $email))['cancel_at_period_end']);
        self::assertSame(200, $this->api('POST', '/api/v1/checkout/resume', as: $email)['status'], 'resume is idempotent');
    }

    public function testActionsOnASubscriptionNeedOne(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');

        $this->assertApiError($this->api('POST', '/api/v1/checkout/cancel', as: $email), 400, 'NO_SUBSCRIPTION');
        $this->assertApiError($this->api('POST', '/api/v1/checkout/resume', as: $email), 400, 'NO_SUBSCRIPTION');
        $this->assertApiError($this->api('POST', '/api/v1/checkout/plan-change/revert', as: $email), 400, 'NO_SUBSCRIPTION');
        $this->assertApiError($this->api('POST', '/api/v1/checkout/portal', as: $email), 400, 'NO_STRIPE_CUSTOMER');
    }

    public function testThePortalOpensForAnAccountWithAGatewayCustomer(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $this->subscribe($email, 'pro');

        $portal = $this->data($this->api('POST', '/api/v1/checkout/portal', as: $email))['portal_url'];

        self::assertMatchesRegularExpression('#/fake-gateway/portal/bps_fake_\w+$#', $portal);
        $this->client->request('GET', (string) parse_url($portal, \PHP_URL_PATH));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('/profile/plans', (string) $this->client->getResponse()->getContent(), 'returns to the plans page');
    }

    public function testThePlansShowALiveCancellationAndAnotherTenantSeesNothingOfIt(): void
    {
        $email = $this->account('GLOBEX01', plan: 'starter');
        $other = $this->account('ACME0001', plan: 'pro');
        $this->subscribe($email, 'pro');
        $this->api('POST', '/api/v1/checkout/cancel', as: $email);

        $plans = $this->data($this->api('GET', '/api/v1/plans', as: $other));

        self::assertSame('pro', $plans['current_plan_id']);
        self::assertFalse($plans['cancel_at_period_end'], 'each account reads its own subscription only');
        $this->assertApiError($this->api('POST', '/api/v1/checkout/cancel', as: $other), 400, 'NO_SUBSCRIPTION');
    }

    private function subscribe(string $email, string $planId, string $interval = 'month'): void
    {
        $url = $this->data($this->api('POST', '/api/v1/checkout/session', ['plan_id' => $planId, 'billing_interval' => $interval], as: $email))['checkout_url'];
        $this->pay($url);
    }

    private function pay(string $checkoutUrl): void
    {
        $this->client->request('POST', parse_url($checkoutUrl, \PHP_URL_PATH).'/pay');
        self::assertSame(302, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
        self::assertStringEndsWith('/profile/plans?checkout=success', (string) $this->client->getResponse()->headers->get('Location'));
        $this->em()->clear();
    }

    /** @param array<string, mixed> $object */
    private function webhook(string $type, array $object): void
    {
        $payload = $this->gateway()->eventPayload($type, $object);
        $this->client->request('POST', '/api/v1/checkout/webhook', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $this->gateway()->sign($payload)], $payload);
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
        $this->em()->clear();
    }

    private function gateway(): FakePaymentGateway
    {
        return static::getContainer()->get(FakePaymentGateway::class);
    }

    private function customerPlan(string $customerId): CustomerPlan
    {
        $this->em()->clear();
        $plan = $this->em()->find(CustomerPlan::class, $customerId);
        self::assertNotNull($plan);

        return $plan;
    }

    private function eventCount(string $customerId, string $type): int
    {
        return (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM domain_event_log WHERE customer_id = ? AND event_type = ?', [$customerId, $type]);
    }
}
