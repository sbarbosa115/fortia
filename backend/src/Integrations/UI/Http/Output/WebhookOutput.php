<?php

namespace App\Integrations\UI\Http\Output;

use OpenApi\Attributes as OA;

/** A webhook (PRD §6.21, §8.11). */
final class WebhookOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $customer_id,
        public readonly string $url,
        #[OA\Property(enum: ['questionnaire.completed'])]
        public readonly string $event_type,
        #[OA\Property(enum: ['POST'])]
        public readonly string $method,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function of(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['customer_id'],
            (string) $row['url'],
            (string) $row['event_type'],
            (string) $row['method'],
            null === $row['created_at'] ? null : (string) $row['created_at'],
            null === $row['updated_at'] ? null : (string) $row['updated_at'],
        );
    }
}
