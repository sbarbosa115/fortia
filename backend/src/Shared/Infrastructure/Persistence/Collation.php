<?php

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;

/**
 * The collation every table uses: DB_COLLATION (config/packages/doctrine.yaml), utf8mb4_0900_ai_ci by default. It is
 * what makes search ignore case and accents (PRD §8.1). A MariaDB without utf8mb4_0900_ai_ci (before 11.4.5) sets an
 * equivalent one: utf8mb4_uca1400_ai_ci (10.10+) or utf8mb4_unicode_520_ci (10.6+). SQL is written with the default
 * and rewritten with apply().
 */
final class Collation
{
    public const DEFAULT = 'utf8mb4_0900_ai_ci';

    public static function of(Connection $connection): string
    {
        $collation = $connection->getParams()['defaultTableOptions']['collation'] ?? self::DEFAULT;
        if (!\is_string($collation) || 1 !== preg_match('/^utf8mb4_[a-z0-9_]+$/', $collation)) {
            throw new \InvalidArgumentException(\sprintf('DB_COLLATION must be a utf8mb4 collation name, got "%s".', get_debug_type($collation)));
        }

        return $collation;
    }

    public static function apply(string $sql, string $collation): string
    {
        return str_replace(self::DEFAULT, $collation, $sql);
    }
}
