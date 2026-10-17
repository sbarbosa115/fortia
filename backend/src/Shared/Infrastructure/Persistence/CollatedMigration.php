<?php

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\Migrations\AbstractMigration;

/**
 * The base of every migration (config/packages/doctrine_migrations.yaml makes new ones extend it): their SQL names
 * utf8mb4_0900_ai_ci, and runs with the configured collation instead (Collation).
 */
abstract class CollatedMigration extends AbstractMigration
{
    /**
     * @param mixed[] $params
     * @param mixed[] $types
     */
    protected function addSql(string $sql, array $params = [], array $types = []): void
    {
        parent::addSql(Collation::apply($sql, Collation::of($this->connection)), $params, $types);
    }
}
