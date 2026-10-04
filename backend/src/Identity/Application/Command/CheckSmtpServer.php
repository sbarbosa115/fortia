<?php

namespace App\Identity\Application\Command;

/**
 * POST /customer/{customer_id}/system-settings/smtp-check: sends a test email to $to through the server in the form
 * (the smtp_* fields over the saved server; the saved password when none is sent), in the account's language.
 * Nothing is saved. 404 CUSTOMER_NOT_FOUND, 422 INVALID_SMTP_SERVER, 502 SMTP_CHECK_FAILED.
 */
final class CheckSmtpServer
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public readonly string $customerId,
        #[\SensitiveParameter]
        public readonly array $fields,
        public readonly string $to,
    ) {
    }
}
