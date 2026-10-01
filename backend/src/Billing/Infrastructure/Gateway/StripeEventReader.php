<?php

namespace App\Billing\Infrastructure\Gateway;

use App\Billing\Application\Port\GatewayEvent;
use App\Billing\Application\Port\InvalidWebhookSignature;

/**
 * Reads a Stripe-shaped event ({id, type, data: {object}}) into a GatewayEvent. Both adapters use it: the fake emits
 * events in Stripe's shape. Handles the current API (invoice.parent.subscription_details) and the older one
 * (invoice.subscription).
 */
final class StripeEventReader
{
    /** @param array<string, mixed> $event */
    public static function read(array $event): GatewayEvent
    {
        $id = $event['id'] ?? null;
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? null;
        if (!\is_string($id) || !\is_string($type) || !\is_array($object)) {
            throw new InvalidWebhookSignature('The event has no id, type or object.');
        }

        return match ($object['object'] ?? null) {
            'checkout.session' => new GatewayEvent(
                $id,
                $type,
                self::ref($object['subscription'] ?? null),
                self::ref($object['customer'] ?? null),
                self::strings($object['metadata'] ?? []),
            ),
            'invoice' => new GatewayEvent(
                $id,
                $type,
                self::ref($object['parent']['subscription_details']['subscription'] ?? $object['subscription'] ?? null),
                self::ref($object['customer'] ?? null),
                self::strings($object['parent']['subscription_details']['metadata'] ?? $object['subscription_details']['metadata'] ?? $object['metadata'] ?? []),
                \is_string($object['billing_reason'] ?? null) ? $object['billing_reason'] : null,
                \is_string($object['id'] ?? null) ? $object['id'] : null,
                \is_int($object['amount_paid'] ?? null) ? $object['amount_paid'] : (\is_int($object['amount_due'] ?? null) ? $object['amount_due'] : null),
                \is_string($object['currency'] ?? null) ? $object['currency'] : null,
            ),
            'subscription' => new GatewayEvent(
                $id,
                $type,
                self::ref($object['id'] ?? null),
                self::ref($object['customer'] ?? null),
                self::strings($object['metadata'] ?? []),
            ),
            default => new GatewayEvent($id, $type, null, self::ref($object['customer'] ?? null)),
        };
    }

    /** An id, or an expanded object's id. */
    private static function ref(mixed $value): ?string
    {
        if (\is_array($value)) {
            $value = $value['id'] ?? null;
        }

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /** @return array<string, string> */
    private static function strings(mixed $metadata): array
    {
        $out = [];
        foreach (\is_array($metadata) ? $metadata : [] as $key => $value) {
            if (\is_scalar($value)) {
                $out[(string) $key] = (string) $value;
            }
        }

        return $out;
    }
}
