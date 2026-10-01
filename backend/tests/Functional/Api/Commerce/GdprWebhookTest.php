<?php

namespace App\Tests\Functional\Api\Commerce;

use App\Commerce\Infrastructure\Platform\FakeCommercePlatform;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** PRD §8.6: the GDPR webhooks are public, verified by HMAC-SHA256 (base64) of the raw body; 401 if it is bad. */
final class GdprWebhookTest extends ApiTestCase
{
    /** @return iterable<string, array{string}> */
    public static function topics(): iterable
    {
        yield 'customers/data_request' => ['customers/data_request'];
        yield 'customers/redact' => ['customers/redact'];
        yield 'shop/redact' => ['shop/redact'];
    }

    #[DataProvider('topics')]
    public function testASignedWebhookIsAcknowledged(string $topic): void
    {
        $body = '{"shop_id":1,"shop_domain":"acme-store.myshopify.com","customer":{"id":7,"email":"x@example.com"}}';

        $response = $this->webhook($topic, $body, base64_encode(hash_hmac('sha256', $body, FakeCommercePlatform::FAKE_WEBHOOK_SECRET, true)));

        self::assertSame(200, $response['status'], '§8.6: they only log and respond 200 — '.$response['body']);
    }

    #[DataProvider('topics')]
    public function testABadOrMissingSignatureIsRefused(string $topic): void
    {
        $body = '{"shop_domain":"acme-store.myshopify.com"}';

        $this->assertApiError($this->webhook($topic, $body, base64_encode(hash_hmac('sha256', $body, 'wrong-secret', true))), 401, 'INVALID_SIGNATURE', '§8.6: 401 if the signature is bad');
        $this->assertApiError($this->webhook($topic, $body.' ', base64_encode(hash_hmac('sha256', $body, FakeCommercePlatform::FAKE_WEBHOOK_SECRET, true))), 401, 'INVALID_SIGNATURE', 'the HMAC covers the raw body');
        $this->assertApiError($this->webhook($topic, $body, null), 401, 'INVALID_SIGNATURE', 'no signature');
    }

    public function testOnlyTheThreeComplianceTopicsExist(): void
    {
        $body = '{}';

        self::assertSame(404, $this->webhook('orders/create', $body, base64_encode(hash_hmac('sha256', $body, FakeCommercePlatform::FAKE_WEBHOOK_SECRET, true)))['status']);
    }

    /** @return array{status: int, json: mixed, body: string} */
    private function webhook(string $topic, string $body, ?string $hmac): array
    {
        return $this->api('POST', '/api/v1/shopify/webhooks/'.$topic, $body, headers: null === $hmac ? [] : ['X-Shopify-Hmac-Sha256' => $hmac, 'X-Shopify-Shop-Domain' => 'acme-store.myshopify.com']);
    }
}
