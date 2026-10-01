<?php

namespace App\Identity\Domain\Model;

/**
 * A console user's email: the username, globally unique and compared in lowercase (PRD §6.2, §8.2). The pattern is
 * the one the apps use (D13, assets/shared/lib/text.ts EMAIL_PATTERN).
 */
final class EmailAddress
{
    public const PATTERN = '/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/';

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function isValid(string $email): bool
    {
        return 1 === preg_match(self::PATTERN, trim($email));
    }
}
