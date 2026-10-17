<?php

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\Migrations\AbstractMigration;

/**
 * The base of every migration (config/packages/doctrine_migrations.yaml makes new ones extend it), so they run the
 * same on MySQL 8 and MariaDB 10.6+: their SQL names utf8mb4_0900_ai_ci and runs with the configured collation
 * (Collation), and a CREATE TABLE without an engine gets InnoDB. DBAL 4 no longer adds one, and a server whose default
 * is MyISAM or Aria refuses the utf8mb4 indexes (1000-byte keys) and has no transactions.
 */
abstract class PortableMigration extends AbstractMigration
{
    public static function portable(string $sql, string $collation): string
    {
        $sql = Collation::apply($sql, $collation);
        if (1 === preg_match('/^\s*CREATE TABLE\b/i', $sql) && 1 !== preg_match('/\bENGINE\s*=/i', $sql)) {
            $sql = rtrim($sql, " \t\n\r;").' ENGINE = InnoDB';
        }

        return $sql;
    }

    /**
     * @param mixed[] $params
     * @param mixed[] $types
     */
    protected function addSql(string $sql, array $params = [], array $types = []): void
    {
        parent::addSql(self::portable($sql, Collation::of($this->connection)), $params, $types);
    }
}
