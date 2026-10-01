<?php

namespace App\Tests\Functional\Api\Billing;

use App\Billing\Application\Port\CheckoutRequest;
use App\Billing\Application\Usage;
use App\Billing\Domain\Model\CustomerPlan;
use App\Billing\Infrastructure\Gateway\Fake\FakePaymentGateway;
use App\Tests\Support\ApiTestCase;

/** PRD §7.4 "Payment webhook events (all idempotent)" and §8.3 POST /checkout/webhook (plain text, 401/500/200). */
final class WebhookTest extends ApiTestCase
{
    public function testABadSignatureIs401InPlainText(): void
    {
        $payload = $this->gateway()->eventPayload('invoice.paid', ['id' => 'in_1', 'object' => 'invoice']);

        $response = $this->post($payload, 't=1,v1=forged');

        self::assertSame(401, $response['status']);
        self::assertStringStartsWith('text/plain', $response['type']);
        self::assertSame(401, $this->post($payload, '')['status'], 'no signature at all');
    }

    public function testTheSameEventTwiceIsAppliedOnce(): void
    {
        [$subscription, $customer] = $this->subscription('GLOBEX01', 'pro');
        $payload = $this->gateway()->eventPayload('invoice.paid', $this->invoice($subscription, $customer, 'subscription_cycle'), 'evt_same');

        $first = $this->post($payload);
        $second = $this->post($payload);

        self::assertSame([200, 200], [$first['status'], $second['status']], 'a redelivery is answered OK');
        self::assertSame('OK', $first['body']);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM billing_payment WHERE customer_id = ?', 'GLOBEX01'), 'the payment is recorded once (deduplicated by event id)');
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM domain_event_log WHERE customer_id = ? AND event_type = 'SubscriptionRenewed'", 'GLOBEX01'), 'SubscriptionRenewed once');
    }

    public function testAPaidInvoiceReassignsThePeriodAndRecordsThePayment(): void
    {
        [$subscription, $customer] = $this->subscription('GLOBEX01', 'pro');
        $this->post($this->gateway()->eventPayload('checkout.session.completed', ['id' => 'cs_1', 'object' => 'checkout.session', 'subscription' => $subscription, 'customer' => $customer, 'metadata' => ['customer_id' => 'GLOBEX01']]));
        $stale = $this->customerPlan('GLOBEX01');
        $stale->assign('pro', '2026-01-01', '2026-01-31', 'month', $this->clock()->now());
        $this->em()->flush();

        self::assertSame(200, $this->post($this->gateway()->eventPayload('invoice.paid', $this->invoice($subscription, $customer, 'subscription_create', 4900)))['status']);

        $plan = $this->customerPlan('GLOBEX01');
        self::assertSame('pro', $plan->planId());
        self::assertSame($this->clock()->today(), $plan->fromAt(), 'PRD §7.4 invoice.paid: reassign the period of the subscription');
        self::assertSame($subscription, $plan->stripeSubscriptionId());
        self::assertSame(4900, (int) $this->em()->getConnection()->fetchOne('SELECT amount FROM billing_payment WHERE customer_id = ?', ['GLOBEX01']));
        self::assertSame(0, $this->rows("SELECT COUNT(*) FROM domain_event_log WHERE customer_id = ? AND event_type = 'SubscriptionRenewed'", 'GLOBEX01'), 'only a subscription_cycle invoice is a renewal');
    }

    public function testAMoreExpensivePlanResetsTheCountersAndACheaperOneDoesNot(): void
    {
        [$subscription, $customer] = $this->subscription('GLOBEX01', 'pro');
        $this->post($this->gateway()->eventPayload('checkout.session.completed', ['id' => 'cs_1', 'object' => 'checkout.session', 'subscription' => $subscription, 'customer' => $customer, 'metadata' => ['customer_id' => 'GLOBEX01']]));
        $this->use('GLOBEX01', 'regular', 4);

        $this->gateway()->changePrice($subscription, 'price_fake_business_month', ['customer_id' => 'GLOBEX01', 'plan_id' => 'business', 'billing_interval' => 'month']);
        $this->deliverFakeEvents();

        self::assertSame('business', $this->customerPlan('GLOBEX01')->planId());
        self::assertSame(0, $this->used('GLOBEX01', 'regular'), 'PRD §7.4: a more expensive plan resets all usage counters to 0');
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM domain_event_log WHERE customer_id = ? AND event_type = 'PlanChanged'", 'GLOBEX01'));

        $this->use('GLOBEX01', 'regular', 2);
        $this->gateway()->changePrice($subscription, 'price_fake_pro_month', ['customer_id' => 'GLOBEX01', 'plan_id' => 'pro', 'billing_interval' => 'month']);
        $this->deliverFakeEvents();

        self::assertSame('pro', $this->customerPlan('GLOBEX01')->planId());
        self::assertSame(2, $this->used('GLOBEX01', 'regular'), 'a cheaper plan keeps the counters');
    }

    public function testAnUpdateWithoutAPlanChangeOnlyReassigns(): void
    {
        [$subscription, $customer] = $this->subscription('GLOBEX01', 'pro');
        $this->post($this->gateway()->eventPayload('checkout.session.completed', ['id' => 'cs_1', 'object' => 'checkout.session', 'subscription' => $subscription, 'customer' => $customer, 'metadata' => ['customer_id' => 'GLOBEX01']]));
        $this->use('GLOBEX01', 'regular', 1);

        $this->post($this->gateway()->eventPayload('customer.subscription.updated', ['id' => $subscription, 'object' => 'subscription', 'customer' => $customer, 'metadata' => ['customer_id' => 'GLOBEX01']]));

        self::assertSame(1, $this->used('GLOBEX01', 'regular'));
        self::assertSame(0, $this->rows("SELECT COUNT(*) FROM domain_event_log WHERE customer_id = ? AND event_type = 'PlanChanged'", 'GLOBEX01'));
    }

    public function testADeletedSubscriptionForgetsItsIdButKeepsThePaidWindow(): void
    {
        [$subscription, $customer] = $this->subscription('GLOBEX01', 'pro');
        $this->post($this->gateway()->eventPayload('checkout.session.completed', ['id' => 'cs_1', 'object' => 'checkout.session', 'subscription' => $subscription, 'customer' => $customer, 'metadata' => ['customer_id' => 'GLOBEX01']]));
        $window = $this->customerPlan('GLOBEX01')->toAt();

        $this->post($this->gateway()->eventPayload('customer.subscription.deleted', ['id' => $subscription, 'object' => 'subscription', 'customer' => $customer, 'metadata' => ['customer_id' => 'GLOBEX01']]));

        $plan = $this->customerPlan('GLOBEX01');
        self::assertNull($plan->stripeSubscriptionId(), 'PRD §7.4: delete the stored subscription id');
        self::assertSame($customer, $plan->stripeCustomerId(), 'the gateway customer stays, for the portal and the next checkout');
        self::assertSame('pro', $plan->planId());
        self::assertSame($window, $plan->toAt(), 'the paid window expires on its own');
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM domain_event_log WHERE customer_id = ? AND event_type = 'SubscriptionCancelled'", 'GLOBEX01'));
    }

    public function testTrialEndingAndFailedPaymentsAreDomainEvents(): void
    {
        [$subscription, $customer] = $this->subscription('GLOBEX01', 'pro');
        $this->post($this->gateway()->eventPayload('checkout.session.completed', ['id' => 'cs_1', 'object' => 'checkout.session', 'subscription' => $subscription, 'customer' => $customer, 'metadata' => ['customer_id' => 'GLOBEX01']]));

        $this->post($this->gateway()->eventPayload('customer.subscription.trial_will_end', ['id' => $subscription, 'object' => 'subscription', 'customer' => $customer, 'metadata' => []]));
        $this->post($this->gateway()->eventPayload('invoice.payment_failed', $this->invoice($subscription, $customer, 'subscription_cycle')));

        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM domain_event_log WHERE customer_id = ? AND event_type = 'TrialWillEnd'", 'GLOBEX01'));
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM domain_event_log WHERE customer_id = ? AND event_type = 'PaymentFailed'", 'GLOBEX01'));
        self::assertSame(0, $this->rows('SELECT COUNT(*) FROM billing_payment WHERE customer_id = ?', 'GLOBEX01'), 'a failed payment is not a payment');
    }

    public function testAnEventTheHandlerCannotApplyIs500SoTheGatewayRetries(): void
    {
        $this->account('GLOBEX01', plan: 'starter');
        $payload = $this->gateway()->eventPayload('invoice.paid', $this->invoice('sub_missing', 'cus_missing', 'subscription_cycle'), 'evt_retry');

        self::assertSame(500, $this->post($payload)['status']);
        self::assertSame(0, $this->rows('SELECT COUNT(*) FROM billing_processed_webhook_event WHERE event_id = ?', 'evt_retry'), 'not marked as processed: the retry is applied');
    }

    public function testOtherEventsAreAcknowledgedAndIgnored(): void
    {
        $response = $this->post($this->gateway()->eventPayload('customer.created', ['id' => 'cus_1', 'object' => 'customer']));

        self::assertSame(200, $response['status']);
    }

    /** @return array{0: string, 1: string} a paid subscription in the fake gateway: [subscription id, customer id] */
    private function subscription(string $customerId, string $planId): array
    {
        $this->account($customerId, plan: 'starter');
        $gateway = $this->gateway();
        $url = $gateway->createCheckoutSession(new CheckoutRequest($customerId, $planId, 'month', "price_fake_{$planId}_month", null, null, 0, 'http://x/success', 'http://x/cancel'));
        $gateway->payCheckout(basename($url), null);
        // Only what the test posts reaches the handler.
        foreach ($gateway->pendingEvents() as $pending) {
            $gateway->markDelivered($pending['seq'], 0);
        }
        $session = $gateway->checkoutSession(basename($url));
        self::assertNotNull($session);
        $row = $this->em()->getConnection()->fetchOne("SELECT data FROM fake_gateway_object WHERE id = ? AND kind = 'checkout_session'", [basename($url)]);
        $data = json_decode((string) $row, true);

        return [(string) $data['subscription'], (string) $data['customer']];
    }

    /** @return array<string, mixed> */
    private function invoice(string $subscription, string $customer, string $reason, int $amount = 4900): array
    {
        return [
            'id' => 'in_'.bin2hex(random_bytes(4)),
            'object' => 'invoice',
            'customer' => $customer,
            'billing_reason' => $reason,
            'amount_paid' => $amount,
            'currency' => 'usd',
            'parent' => ['type' => 'subscription_details', 'subscription_details' => ['subscription' => $subscription, 'metadata' => ['customer_id' => 'GLOBEX01']]],
        ];
    }

    /** @return array{status: int, body: string, type: string} */
    private function post(string $payload, ?string $signature = null): array
    {
        $this->client->request('POST', '/api/v1/checkout/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature ?? $this->gateway()->sign($payload),
        ], $payload);
        $response = $this->client->getResponse();
        $this->em()->clear();

        return ['status' => $response->getStatusCode(), 'body' => (string) $response->getContent(), 'type' => (string) $response->headers->get('Content-Type')];
    }

    private function deliverFakeEvents(): void
    {
        // Any request delivers what the fake gateway queued (FakeWebhookDelivery).
        $this->client->request('GET', '/api/v1/health');
        $this->em()->clear();
    }

    private function use(string $customerId, string $feature, int $amount): void
    {
        static::getContainer()->get(Usage::class)->record($customerId, $feature, $amount);
        $this->em()->flush();
        $this->em()->clear();
    }

    private function used(string $customerId, string $feature): int
    {
        return static::getContainer()->get(Usage::class)->current($customerId)[$feature] ?? 0;
    }

    private function customerPlan(string $customerId): CustomerPlan
    {
        $this->em()->clear();
        $plan = $this->em()->find(CustomerPlan::class, $customerId);
        self::assertNotNull($plan);

        return $plan;
    }

    private function rows(string $sql, string $param): int
    {
        return (int) $this->em()->getConnection()->fetchOne($sql, [$param]);
    }

    private function gateway(): FakePaymentGateway
    {
        return static::getContainer()->get(FakePaymentGateway::class);
    }
}
