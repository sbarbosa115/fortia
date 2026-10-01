<?php

namespace App\Integrations\Domain;

/** The X-Signature header of an outgoing webhook (PRD §7.14): "sha256=" + hex HMAC-SHA256 of the exact body sent. */
final class WebhookSignature
{
    public static function of(string $body, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $secret);
    }
}
