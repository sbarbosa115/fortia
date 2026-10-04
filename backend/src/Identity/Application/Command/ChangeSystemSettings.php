<?php

namespace App\Identity\Application\Command;

/**
 * PATCH /customer/{customer_id}/system-settings: the fields sent, already shape-checked (smtp_* and openai_api_key).
 * 404 CUSTOMER_NOT_FOUND, 422 INVALID_SMTP_SERVER.
 */
final class ChangeSystemSettings
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public readonly string $customerId,
        #[\SensitiveParameter]
        public readonly array $fields,
    ) {
    }
}
