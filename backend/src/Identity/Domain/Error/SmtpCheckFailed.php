<?php

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\UpstreamFailed;

/**
 * The server check did not deliver the test email (502 SMTP_CHECK_FAILED). details.reason says where it failed:
 * connection, tls, authentication, blocked or refused, without echoing what the server answered.
 */
final class SmtpCheckFailed extends UpstreamFailed
{
    public const REASONS = ['connection', 'tls', 'authentication', 'blocked', 'refused'];

    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct('SMTP_CHECK_FAILED', 'The test email could not be sent through this server.', ['reason' => $reason], $previous);
    }

    /** Where a transport failure happened, from the mailer's message. */
    public static function reasonOf(string $message): string
    {
        $text = strtolower($message);

        return match (true) {
            str_starts_with($text, 'blocked:') => 'blocked',
            str_contains($text, 'authenticat') || str_contains($text, 'username') || str_contains($text, 'password') || str_contains($text, '535') => 'authentication',
            str_contains($text, 'tls') || str_contains($text, 'ssl') || str_contains($text, 'crypto') || str_contains($text, 'certificate') => 'tls',
            str_contains($text, 'connection') || str_contains($text, 'resolve') || str_contains($text, 'timed out') || str_contains($text, 'refused') => 'connection',
            default => 'refused',
        };
    }
}
