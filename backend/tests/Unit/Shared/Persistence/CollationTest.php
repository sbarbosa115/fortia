<?php

namespace App\Tests\Unit\Shared\Persistence;

use App\Shared\Infrastructure\Persistence\Collation;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class CollationTest extends TestCase
{
    public function testTheDefaultCollationLeavesTheSqlAsWritten(): void
    {
        $sql = 'CREATE TABLE t (name VARCHAR(10) COLLATE `utf8mb4_0900_ai_ci`) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`';

        self::assertSame($sql, Collation::apply($sql, Collation::DEFAULT));
    }

    public function testAnotherCollationReplacesEveryMentionOfTheDefault(): void
    {
        $sql = Collation::apply('CREATE TABLE t (name VARCHAR(10) COLLATE `utf8mb4_0900_ai_ci`) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`', 'utf8mb4_uca1400_ai_ci');

        self::assertSame('CREATE TABLE t (name VARCHAR(10) COLLATE `utf8mb4_uca1400_ai_ci`) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci`', $sql, 'a MariaDB before 11.4.5 has no utf8mb4_0900_ai_ci');
    }

    public function testTheCollationComesFromTheConnectionsTableOptions(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'defaultTableOptions' => ['collation' => 'utf8mb4_unicode_520_ci']]);

        self::assertSame('utf8mb4_unicode_520_ci', Collation::of($connection), 'DB_COLLATION reaches the connection through doctrine.yaml');
    }

    public function testAConnectionWithoutOneUsesTheDefault(): void
    {
        self::assertSame(Collation::DEFAULT, Collation::of(DriverManager::getConnection(['driver' => 'pdo_mysql'])));
    }

    public function testAValueThatIsNotACollationNameIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Collation::of(DriverManager::getConnection(['driver' => 'pdo_mysql', 'defaultTableOptions' => ['collation' => 'x`; DROP TABLE t; --']]));
    }
}
