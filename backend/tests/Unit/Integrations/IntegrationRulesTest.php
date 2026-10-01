<?php

namespace App\Tests\Unit\Integrations;

use App\Integrations\Domain\ApiKeyFormat;
use App\Integrations\Domain\Model\ApiKey;
use App\Integrations\Domain\Model\WebhookDelivery;
use App\Integrations\Domain\WebhookRetryPolicy;
use App\Integrations\Domain\WebhookSignature;
use PHPUnit\Framework\TestCase;

/** The rules of API keys (PRD §6.20, §8.11) and of outgoing webhook delivery (§7.14, D19). */
final class IntegrationRulesTest extends TestCase
{
    public function testANewKeyIsQairePlusSixtyFourHexAndItsIdIsItsSha256(): void
    {
        $key = ApiKeyFormat::generate();

        self::assertMatchesRegularExpression('/^QAIRE-[0-9a-f]{64}$/', $key, '§8.11: the key is "QAIRE-" + 64 hex');
        self::assertSame(hash('sha256', $key), ApiKey::hash($key), '§6.20: the id is the SHA-256 of the key');
        self::assertNotSame($key, ApiKeyFormat::generate(), 'every key is random');
    }

    public function testOnlyWellFormedKeysAreLookedUp(): void
    {
        self::assertTrue(ApiKeyFormat::isWellFormed('QAIRE-'.str_repeat('a1', 32)));
        self::assertFalse(ApiKeyFormat::isWellFormed(null));
        self::assertFalse(ApiKeyFormat::isWellFormed('QAIRE-xyz'));
        self::assertFalse(ApiKeyFormat::isWellFormed('qaire-'.str_repeat('a1', 32)));
    }

    public function testARevokedOrExpiredKeyNoLongerAuthenticates(): void
    {
        $now = new \DateTimeImmutable('2026-09-30T12:00:00Z');
        $key = new ApiKey(ApiKey::hash('k'), 'ACME0001', 'CI', $now, $now->modify('+7 days'));

        self::assertTrue($key->isUsableAt($now));
        self::assertFalse($key->isUsableAt($now->modify('+7 days')), '§8.11: an expired key is INVALID_API_KEY');
        $key->revoke();
        self::assertFalse($key->isUsableAt($now), '§8.11: a revoked key is INVALID_API_KEY');
    }

    public function testTheSignatureIsTheHexHmacSha256OfTheExactBody(): void
    {
        $body = '{"event_type":"questionnaire.completed"}';

        self::assertSame('sha256='.hash_hmac('sha256', $body, 'secret'), WebhookSignature::of($body, 'secret'), '§7.14: X-Signature: sha256=<hex HMAC-SHA256 of the body>');
    }

    public function testFailedDeliveriesAreRetriedWithAGrowingBackoffAndThenGiveUp(): void
    {
        $now = new \DateTimeImmutable('2026-09-30T12:00:00Z');

        self::assertEquals($now->modify('+1 minute'), WebhookRetryPolicy::nextAttemptAt(1, $now), 'D19: the first retry comes a minute later');
        self::assertEquals($now->modify('+5 minutes'), WebhookRetryPolicy::nextAttemptAt(2, $now));
        self::assertEquals($now->modify('+30 minutes'), WebhookRetryPolicy::nextAttemptAt(3, $now));
        self::assertEquals($now->modify('+2 hours'), WebhookRetryPolicy::nextAttemptAt(4, $now));
        self::assertEquals($now->modify('+6 hours'), WebhookRetryPolicy::nextAttemptAt(5, $now));
        self::assertNull(WebhookRetryPolicy::nextAttemptAt(WebhookRetryPolicy::MAX_ATTEMPTS, $now), 'D19: after the last attempt the delivery is failed');
    }

    public function testADeliveryLogsEveryAttempt(): void
    {
        $now = new \DateTimeImmutable('2026-09-30T12:00:00Z');
        $delivery = new WebhookDelivery('d1', 'w1', 'ACME0001', 'questionnaire.completed', ['a' => 1], $now);

        self::assertSame(WebhookDelivery::PENDING, $delivery->status());
        $delivery->recordFailure(500, 'HTTP 500', $now->modify('+1 minute'), $now);
        self::assertSame(WebhookDelivery::PENDING, $delivery->status(), 'a failure with a retry left stays pending');
        self::assertSame(1, $delivery->attempts());
        self::assertSame('HTTP 500', $delivery->lastError());
        $delivery->recordSuccess(204, $now->modify('+1 minute'));
        self::assertSame(WebhookDelivery::DELIVERED, $delivery->status());
        self::assertSame(2, $delivery->attempts());
        self::assertNull($delivery->lastError());
        self::assertNull($delivery->nextAttemptAt());

        $failed = new WebhookDelivery('d2', 'w1', 'ACME0001', 'questionnaire.completed', [], $now);
        $failed->recordFailure(null, 'Timeout', null, $now);
        self::assertSame(WebhookDelivery::FAILED, $failed->status(), 'no retry left: failed');
    }
}
