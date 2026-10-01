<?php

namespace App\Integrations\UI\Http\Output;

use OpenApi\Attributes as OA;

/** One entry of a webhook's delivery log (D19). */
final class WebhookDeliveryOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $webhook_id,
        public readonly string $event_type,
        #[OA\Property(enum: ['pending', 'delivered', 'failed'])]
        public readonly string $status,
        public readonly int $attempts,
        public readonly ?int $last_status_code,
        public readonly ?string $last_error,
        /** When the next retry is due (pending only). */
        public readonly ?string $next_attempt_at,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function of(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['webhook_id'],
            (string) $row['event_type'],
            (string) $row['status'],
            (int) $row['attempts'],
            null === $row['last_status_code'] ? null : (int) $row['last_status_code'],
            null === $row['last_error'] ? null : (string) $row['last_error'],
            null === $row['next_attempt_at'] ? null : (string) $row['next_attempt_at'],
            null === $row['created_at'] ? null : (string) $row['created_at'],
            null === $row['updated_at'] ? null : (string) $row['updated_at'],
        );
    }
}
