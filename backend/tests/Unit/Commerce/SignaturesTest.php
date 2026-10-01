<?php

namespace App\Tests\Unit\Commerce;

use App\Commerce\Domain\Error\InvalidOAuthState;
use App\Commerce\Domain\OAuthState;
use App\Commerce\Domain\WebhookSignature;
use PHPUnit\Framework\TestCase;

/** D5 (signed OAuth nonce) and §8.6 (webhook HMAC-SHA256, base64). */
final class SignaturesTest extends TestCase
{
    private const SECRET = 'test-secret';

    public function testAStateIsTrustedOnlyWhenSignedUnexpiredAndForTheSameShop(): void
    {
        $now = new \DateTimeImmutable('2026-10-01T10:00:00Z');
        $state = OAuthState::issue('ACME0001', 'acme.myshopify.com', $now, self::SECRET);

        self::assertSame('ACME0001', OAuthState::verify($state, 'acme.myshopify.com', $now->modify('+14 minutes'), self::SECRET));
        $this->assertRefused($state, 'acme.myshopify.com', $now->modify('+16 minutes'), self::SECRET, 'D5: the nonce expires after 15 minutes');
        $this->assertRefused($state, 'other.myshopify.com', $now, self::SECRET, 'a state is bound to its shop');
        $this->assertRefused($state, 'acme.myshopify.com', $now, 'another-secret', 'only our secret signs states');
        $this->assertRefused('ACME0001', 'acme.myshopify.com', $now, self::SECRET, 'D5: the legacy state (a bare customer_id) is refused');
        $this->assertRefused(null, 'acme.myshopify.com', $now, self::SECRET, 'a missing state is refused');
    }

    public function testATamperedCustomerIdBreaksTheSignature(): void
    {
        $now = new \DateTimeImmutable('2026-10-01T10:00:00Z');
        [$payload, $signature] = explode('.', OAuthState::issue('ACME0001', 'acme.myshopify.com', $now, self::SECRET));
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        $data['c'] = 'GLOBEX01';
        $forged = rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=').'.'.$signature;

        $this->assertRefused($forged, 'acme.myshopify.com', $now, self::SECRET, 'D5: nobody can bind a store to another account');
    }

    public function testEveryStateHasItsOwnNonce(): void
    {
        $now = new \DateTimeImmutable();

        self::assertNotSame(OAuthState::issue('ACME0001', 'acme.myshopify.com', $now, self::SECRET), OAuthState::issue('ACME0001', 'acme.myshopify.com', $now, self::SECRET));
    }

    public function testAWebhookSignatureIsTheBase64HmacOfTheRawBody(): void
    {
        $body = '{"shop_domain":"acme.myshopify.com"}';
        $header = base64_encode(hash_hmac('sha256', $body, self::SECRET, true));

        self::assertTrue(WebhookSignature::isValid($body, $header, self::SECRET));
        self::assertFalse(WebhookSignature::isValid($body.' ', $header, self::SECRET), 'any change to the body breaks it');
        self::assertFalse(WebhookSignature::isValid($body, $header, 'other'), 'another secret');
        self::assertFalse(WebhookSignature::isValid($body, null, self::SECRET), 'no header');
        self::assertFalse(WebhookSignature::isValid($body, base64_encode(hash_hmac('sha256', $body, '', true)), ''), 'without a configured secret nothing is valid');
    }

    private function assertRefused(?string $state, string $shop, \DateTimeImmutable $at, string $secret, string $why): void
    {
        try {
            OAuthState::verify($state, $shop, $at, $secret);
            self::fail($why);
        } catch (InvalidOAuthState $e) {
            self::assertSame('INVALID_REQUEST', $e->errorCode(), $why);
        }
    }
}
