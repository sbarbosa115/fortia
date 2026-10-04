<?php

namespace App\Identity\Domain\Model;

use App\Identity\Domain\Error\InvalidSmtpServer;

/**
 * The SMTP server an account sends its emails through. The password is the sealed (encrypted) value, never the
 * clear text. Encryption: "ssl" (implicit TLS, usually port 465), "tls" (STARTTLS required, usually 587) or "none".
 */
final class SmtpConfiguration
{
    public const ENCRYPTIONS = ['tls', 'ssl', 'none'];

    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $encryption,
        public readonly ?string $username,
        public readonly ?string $sealedPassword,
        public readonly string $fromEmail,
        public readonly ?string $fromName,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9.-]{0,253}[A-Za-z0-9])?$/', $host)) {
            throw new InvalidSmtpServer('smtp_host', 'Enter the server name only, e.g. smtp.example.com.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidSmtpServer('smtp_port', 'The port is a number from 1 to 65535.');
        }
        if (!\in_array($encryption, self::ENCRYPTIONS, true)) {
            throw new InvalidSmtpServer('smtp_encryption', 'The encryption is tls, ssl or none.');
        }
        if (false === filter_var($fromEmail, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidSmtpServer('smtp_from_email', 'The sender is a valid email address.');
        }
        if (null !== $sealedPassword && null === $username) {
            throw new InvalidSmtpServer('smtp_username', 'A password needs a username.');
        }
    }

    /** Logs in to the server (a username was given). */
    public function authenticates(): bool
    {
        return null !== $this->username;
    }
}
