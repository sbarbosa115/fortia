<?php

namespace App\Commerce\Domain;

use App\Commerce\Domain\Error\InvalidOAuthState;

/**
 * The `state` of the e-commerce platform's OAuth (D5: the legacy state was the bare customer_id, so anyone could
 * bind their store to another account). It is now a signed, expiring nonce bound to the account and the shop:
 *
 *     base64url({"c": customer_id, "s": shop, "n": random nonce, "e": expiry unix time}) . "." . base64url(HMAC-SHA256)
 *
 * The callback only trusts the customer id inside a state whose signature, expiry and shop all check out.
 */
final class OAuthState
{
    public const TTL_SECONDS = 900;

    public static function issue(string $customerId, string $shop, \DateTimeImmutable $now, string $secret): string
    {
        $payload = self::encode((string) json_encode([
            'c' => $customerId,
            's' => $shop,
            'n' => bin2hex(random_bytes(16)),
            'e' => $now->getTimestamp() + self::TTL_SECONDS,
        ]));

        return $payload.'.'.self::encode(hash_hmac('sha256', $payload, self::key($secret), true));
    }

    /**
     * The customer id the state was issued for.
     *
     * @throws InvalidOAuthState when it is malformed, forged, expired or for another shop
     */
    public static function verify(?string $state, string $shop, \DateTimeImmutable $now, string $secret): string
    {
        $parts = explode('.', (string) $state);
        if (2 !== \count($parts) || '' === $parts[0] || '' === $parts[1]) {
            throw new InvalidOAuthState();
        }
        [$payload, $signature] = $parts;
        if (!hash_equals(self::encode(hash_hmac('sha256', $payload, self::key($secret), true)), $signature)) {
            throw new InvalidOAuthState();
        }
        $data = json_decode((string) self::decode($payload), true);
        if (!\is_array($data) || !\is_string($data['c'] ?? null) || !\is_string($data['s'] ?? null) || !\is_int($data['e'] ?? null)) {
            throw new InvalidOAuthState();
        }
        if ($data['e'] < $now->getTimestamp() || !hash_equals($data['s'], $shop)) {
            throw new InvalidOAuthState();
        }

        return $data['c'];
    }

    /** A key of its own, derived from the app secret, so a state can never pass for another signed value. */
    private static function key(string $secret): string
    {
        return hash_hmac('sha256', 'commerce-oauth-state', $secret, true);
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): string|false
    {
        return base64_decode(strtr($text, '-_', '+/'), true);
    }
}
