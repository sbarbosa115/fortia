<?php

namespace App\Tests\Functional\Api\Integrations;

use App\Billing\Application\Usage;
use App\Integrations\Application\Port\WebhookResponse;
use App\Integrations\Application\RetryDueWebhooks;
use App\Integrations\Domain\Model\WebhookDelivery;
use App\Tests\Functional\Api\Responses\SessionFixtures;
use App\Tests\Support\ApiTestCase;

/**
 * questionnaire.completed delivery (PRD §7.7 step 3, §7.14, §16.3 #14) with retries and a delivery log (D19). The
 * transport is RecordingWebhookTransport: nothing leaves the test.
 */
final class WebhookDispatchTest extends ApiTestCase
{
    use SessionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock()->set('2026-09-30T12:00:00Z');
        $this->transport()->reset();
    }

    public function testACompletedSessionIsPostedSignedToEveryWebhookAndCountsEachDelivery(): void
    {
        $owner = $this->account('ACME0001');
        $first = $this->webhook($owner, 'https://hooks.acme.test/one');
        $this->webhook($owner, 'https://hooks.acme.test/two');
        $questionnaireId = $this->questionnaire('ACME0001', questions: [self::textQuestion('q1', 'Tell us about you')]);

        $sessionId = $this->complete($questionnaireId, ['q1' => 'Hello']);

        $requests = $this->transport()->requests();
        self::assertEqualsCanonicalizing(['https://hooks.acme.test/one', 'https://hooks.acme.test/two'], array_column($requests, 'url'), '§7.14: POST to each subscribed URL');
        $body = $requests[0]['body'];
        self::assertSame(
            ['customer_id' => 'ACME0001', 'event_type' => 'questionnaire.completed', 'questionnaire_id' => $questionnaireId, 'data' => ['id' => $sessionId, 'answers' => [['title' => 'Tell us about you', 'value' => 'Hello']]]],
            json_decode($body, true),
            '§7.14: the body',
        );
        self::assertSame('sha256='.hash_hmac('sha256', $body, (string) $_SERVER['WEBHOOK_SIGNING_SECRET']), $requests[0]['headers']['X-Signature'], '§7.14, §16.3 #14: a receiver verifies the HMAC-SHA256 of the body');
        self::assertSame('questionnaire.completed', $requests[0]['headers']['X-Event-Type']);
        self::assertSame(2, static::getContainer()->get(Usage::class)->current('ACME0001')['webhook'] ?? 0, '§7.2: each successful delivery counts one "webhook"');
        $log = $this->data($this->api('GET', '/api/v1/webhooks/'.$first.'/deliveries', as: $owner));
        self::assertSame('delivered', $log[0]['status'], 'D19: the delivery log');
        self::assertSame(1, $log[0]['attempts']);
        self::assertSame(204, $log[0]['last_status_code']);
    }

    public function testTheCapacityIsCheckedFirstAndARejectedPlanDeliversNothing(): void
    {
        $owner = $this->account('ACME0001');
        $webhook = $this->webhook($owner, 'https://hooks.acme.test/one');
        static::getContainer()->get(Usage::class)->set('ACME0001', ['webhook' => 5000]);
        $this->em()->flush();

        $this->complete($this->questionnaire('ACME0001'), ['q1' => 'Hello']);

        self::assertSame([], $this->transport()->requests(), '§7.14: the webhook capacity is checked first; if rejected, nothing is delivered');
        self::assertSame([], $this->data($this->api('GET', '/api/v1/webhooks/'.$webhook.'/deliveries', as: $owner)), 'nothing is logged either');
    }

    public function testAnAccountWithoutWebhooksSendsNothingAndAnotherAccountsWebhooksNeverFire(): void
    {
        $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $this->webhook($globex, 'https://hooks.globex.test/');

        $this->complete($this->questionnaire('ACME0001'), ['q1' => 'Hello']);

        self::assertSame([], $this->transport()->requests());
    }

    public function testAFailedDeliveryIsRetriedWithBackoffUntilItSucceeds(): void
    {
        $owner = $this->account('ACME0001');
        $webhook = $this->webhook($owner, 'https://hooks.acme.test/flaky');
        $this->transport()->willAnswer(WebhookResponse::status(500), WebhookResponse::failed('Timeout'));

        $this->complete($this->questionnaire('ACME0001'), ['q1' => 'Hello']);

        $log = $this->data($this->api('GET', '/api/v1/webhooks/'.$webhook.'/deliveries', as: $owner));
        self::assertSame('pending', $log[0]['status'], 'D19: a failure is retried, not dropped');
        self::assertSame('HTTP 500', $log[0]['last_error']);
        self::assertSame('2026-09-30T12:01:00Z', $log[0]['next_attempt_at'], 'D19: first retry after a minute');
        self::assertSame(0, static::getContainer()->get(Usage::class)->current('ACME0001')['webhook'] ?? 0, 'a failed delivery does not count');

        self::assertSame(0, $this->retries()->run(), 'not due yet');
        $this->clock()->set('2026-09-30T12:01:00Z');
        self::assertSame(1, $this->retries()->run());
        $log = $this->data($this->api('GET', '/api/v1/webhooks/'.$webhook.'/deliveries', as: $owner));
        self::assertSame('Timeout', $log[0]['last_error']);
        self::assertSame('2026-09-30T12:06:00Z', $log[0]['next_attempt_at'], 'D19: the backoff grows');

        $this->clock()->set('2026-09-30T12:06:00Z');
        $this->retries()->run();
        $log = $this->data($this->api('GET', '/api/v1/webhooks/'.$webhook.'/deliveries', as: $owner));
        self::assertSame('delivered', $log[0]['status']);
        self::assertSame(3, $log[0]['attempts']);
        $bodies = array_column($this->transport()->requests(), 'body');
        self::assertCount(3, $bodies);
        self::assertSame($bodies[0], $bodies[2], 'a retry sends the same body (and signature) as the first attempt');
        self::assertSame(1, static::getContainer()->get(Usage::class)->current('ACME0001')['webhook'] ?? 0);
    }

    public function testADeliveryThatKeepsFailingEndsAsFailed(): void
    {
        $owner = $this->account('ACME0001');
        $webhook = $this->webhook($owner, 'https://hooks.acme.test/down');
        $this->transport()->willAnswer(...array_fill(0, 6, WebhookResponse::status(503)));
        $this->complete($this->questionnaire('ACME0001'), ['q1' => 'Hello']);

        foreach (['12:01', '12:06', '12:36', '14:36', '20:36'] as $time) {
            $this->clock()->set('2026-09-30T'.$time.':00Z');
            self::assertSame(1, $this->retries()->run(), 'retry due at '.$time);
        }

        $this->em()->clear();
        $delivery = $this->em()->getRepository(WebhookDelivery::class)->findOneBy(['webhookId' => $webhook]);
        self::assertNotNull($delivery);
        self::assertSame(WebhookDelivery::FAILED, $delivery->status(), 'D19: after the last retry the delivery is failed and stays in the log');
        self::assertSame(6, $delivery->attempts());
        $this->clock()->set('2026-10-02T00:00:00Z');
        self::assertSame(0, $this->retries()->run(), 'a failed delivery is not retried again');
    }

    private function webhook(string $as, string $url): string
    {
        return (string) $this->data($this->api('POST', '/api/v1/webhooks', ['url' => $url, 'event_type' => 'questionnaire.completed', 'method' => 'POST'], as: $as), 201)['id'];
    }

    /** @param array<string, string> $values */
    private function complete(string $questionnaireId, array $values): string
    {
        $session = $this->startSession($questionnaireId);
        $this->data($this->api('POST', '/api/v1/questionnaire/session', self::answered($session, $values)));

        return (string) $session['session_id'];
    }

    private function transport(): RecordingWebhookTransport
    {
        return static::getContainer()->get(RecordingWebhookTransport::class);
    }

    private function retries(): RetryDueWebhooks
    {
        return static::getContainer()->get(RetryDueWebhooks::class);
    }
}
