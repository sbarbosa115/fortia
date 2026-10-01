<?php

namespace App\Identity\Application;

/**
 * The rules of password recovery codes (PRD §8.2, §13.1): six digits, valid for an hour, stored as a SHA-256 hash,
 * and burnt after five wrong attempts. The outcome of a confirmation is a result, not an exception, so a wrong
 * attempt is counted even though the request fails.
 */
final class PasswordRecovery
{
    public const VALID_MINUTES = 60;
    public const MAX_ATTEMPTS = 5;
    public const MIN_PASSWORD_LENGTH = 8;

    public const OK = 'OK';
    public const INVALID_RESET_CODE = 'INVALID_RESET_CODE';
    public const EXPIRED_RESET_CODE = 'EXPIRED_RESET_CODE';
    public const INVALID_PASSWORD = 'INVALID_PASSWORD';
    public const TOO_MANY_ATTEMPTS = 'TOO_MANY_ATTEMPTS';

    public static function newCode(): string
    {
        return \sprintf('%06d', random_int(0, 999999));
    }

    public static function hash(string $code): string
    {
        return hash('sha256', trim($code));
    }

    public static function matches(string $hash, string $code): bool
    {
        return hash_equals($hash, self::hash($code));
    }
}
