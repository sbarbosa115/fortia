<?php

namespace App\Commerce\Domain;

/**
 * The e-commerce platform's webhook signature (PRD §8.6 GDPR webhooks, §14.2): HMAC-SHA256 of the raw body with the
 * app secret, base64-encoded, in the X-Shopify-Hmac-Sha256 header. Compared in constant time; without a secret
 * nothing is valid.
 */
final class WebhookSignature
{
    public static function of(string $body, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    public static function isValid(string $body, ?string $header, string $secret): bool
    {
        if ('' === $secret || null === $header || '' === trim($header)) {
            return false;
        }

        return hash_equals(self::of($body, $secret), trim($header));
    }
}
