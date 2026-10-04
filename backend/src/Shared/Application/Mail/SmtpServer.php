<?php

namespace App\Shared\Application\Mail;

/**
 * An account's own SMTP server, opened for sending (the password in clear, only in memory).
 * Encryption: "ssl" (implicit TLS), "tls" (STARTTLS required) or "none".
 */
final class SmtpServer
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $encryption,
        public readonly ?string $username,
        #[\SensitiveParameter]
        public readonly ?string $password,
        public readonly string $fromEmail,
        public readonly ?string $fromName,
    ) {
    }
}
