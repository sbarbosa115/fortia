<?php

namespace App\Identity\Application\Command;

/**
 * PATCH /customer/{customer_id}/settings (PRD §8.3): the fields sent, already shape-checked. Returns the account's
 * CustomerSettings after the change. 404 CUSTOMER_NOT_FOUND.
 */
final class ChangeSettings
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public readonly string $customerId,
        public readonly array $fields,
    ) {
    }
}
