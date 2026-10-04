<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Error\InvalidSmtpServer;
use App\Identity\Domain\Model\SmtpConfiguration;
use App\Shared\Application\Security\SecretBox;

/**
 * The smtp_* fields of a PATCH (or of a server check) over the saved server: a field not sent keeps its value, an
 * empty smtp_host removes the server, an empty smtp_password removes the password and an absent one keeps it.
 */
final class SmtpFields
{
    public const FIELDS = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name'];

    /** @param array<string, mixed> $fields */
    public static function touches(array $fields): bool
    {
        return [] !== array_intersect(self::FIELDS, array_keys($fields));
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return SmtpConfiguration|null null when the result has no host (no server of its own)
     *
     * @throws InvalidSmtpServer
     */
    public static function merge(?SmtpConfiguration $current, array $fields, SecretBox $box): ?SmtpConfiguration
    {
        $value = static function (string $field, mixed $saved) use ($fields): mixed {
            if (!\array_key_exists($field, $fields)) {
                return $saved;
            }
            $sent = $fields[$field];
            if (\is_string($sent)) {
                return '' === trim($sent) ? null : trim($sent);
            }

            return $sent;
        };
        $host = $value('smtp_host', $current?->host);
        if (null === $host) {
            return null;
        }
        $port = $value('smtp_port', $current?->port);
        $encryption = $value('smtp_encryption', $current?->encryption);
        $fromEmail = $value('smtp_from_email', $current?->fromEmail);
        foreach (['smtp_port' => $port, 'smtp_encryption' => $encryption, 'smtp_from_email' => $fromEmail] as $field => $required) {
            if (null === $required) {
                throw new InvalidSmtpServer($field, 'The server needs a port, an encryption and a sender email.');
            }
        }
        if (\array_key_exists('smtp_password', $fields)) {
            $sent = $fields['smtp_password'];
            $password = \is_string($sent) && '' !== $sent ? $box->seal($sent) : null;
        } else {
            $password = $current?->sealedPassword;
        }

        return new SmtpConfiguration(
            (string) $host,
            (int) $port,
            (string) $encryption,
            self::nullableString($value('smtp_username', $current?->username)),
            $password,
            (string) $fromEmail,
            self::nullableString($value('smtp_from_name', $current?->fromName)),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : (string) $value;
    }
}
