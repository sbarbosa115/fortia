<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * The id formats of PRD §6: UUIDv4 by default, 8 random alphanumerics for a customer, a 20-character short id for a
 * flow, 15 characters for a flow state, and "job_" + a time-sortable id for a job.
 */
final class Ids
{
    private const ALPHANUMERIC = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const UUID4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return \sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }

    public static function isUuid4(string $value): bool
    {
        return 1 === preg_match(self::UUID4, strtolower($value));
    }

    /** A random alphanumeric id: 8 for a customer, 20 for a flow, 15 for a flow state. */
    public static function alphanumeric(int $length): string
    {
        $id = '';
        $max = \strlen(self::ALPHANUMERIC) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $id .= self::ALPHANUMERIC[random_int(0, $max)];
        }

        return $id;
    }

    public static function customerId(): string
    {
        return self::alphanumeric(8);
    }

    /** "job_" + a ULID: 48 bits of milliseconds and 80 random bits, Crockford base32, sortable by creation time. */
    public static function jobId(?\DateTimeImmutable $at = null): string
    {
        $ms = (int) ($at ?? new \DateTimeImmutable())->format('Uv');
        $time = '';
        for ($i = 0; $i < 10; ++$i) {
            $time = self::CROCKFORD[$ms % 32].$time;
            $ms = intdiv($ms, 32);
        }
        $random = '';
        for ($i = 0; $i < 16; ++$i) {
            $random .= self::CROCKFORD[random_int(0, 31)];
        }

        return 'job_'.$time.$random;
    }

    /** Lowercase hex, e.g. the 4 random characters of an onboarding slug. */
    public static function hex(int $length): string
    {
        return substr(bin2hex(random_bytes(intdiv($length + 1, 2))), 0, $length);
    }
}
