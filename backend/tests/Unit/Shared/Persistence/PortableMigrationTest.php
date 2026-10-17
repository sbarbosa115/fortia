<?php

namespace App\Tests\Unit\Shared\Persistence;

use App\Shared\Infrastructure\Persistence\PortableMigration;
use PHPUnit\Framework\TestCase;

final class PortableMigrationTest extends TestCase
{
    public function testACreateTableWithoutAnEngineGetsInnoDb(): void
    {
        self::assertSame(
            'CREATE TABLE t (id INT NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB',
            PortableMigration::portable('CREATE TABLE t (id INT NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`', 'utf8mb4_unicode_520_ci'),
            'a server defaulting to MyISAM refuses utf8mb4 indexes over 1000 bytes',
        );
    }

    public function testAnEngineAlreadyNamedIsKept(): void
    {
        $sql = 'CREATE TABLE t (id INT NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB';

        self::assertSame($sql, PortableMigration::portable($sql, 'utf8mb4_0900_ai_ci'));
    }

    public function testOtherStatementsOnlyGetTheCollation(): void
    {
        self::assertSame(
            'ALTER TABLE t ADD name VARCHAR(10) NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`',
            PortableMigration::portable('ALTER TABLE t ADD name VARCHAR(10) NOT NULL COLLATE `utf8mb4_0900_ai_ci`', 'utf8mb4_uca1400_ai_ci'),
        );
        self::assertSame('DROP TABLE t', PortableMigration::portable('DROP TABLE t', 'utf8mb4_uca1400_ai_ci'));
    }
}
