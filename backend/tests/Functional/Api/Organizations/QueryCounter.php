<?php

namespace App\Tests\Functional\Api\Organizations;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * Counts the SQL statements the app runs (tests only, config/services/organizations.yaml), so a test can prove a
 * listing's query count does not grow with its rows (steps/04 §4.1, "no N+1").
 */
final class QueryCounter implements Middleware
{
    public static int $count = 0;

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                return new class(parent::connect($params)) extends AbstractConnectionMiddleware {
                    public function prepare(string $sql): Statement
                    {
                        ++QueryCounter::$count;

                        return parent::prepare($sql);
                    }

                    public function query(string $sql): Result
                    {
                        ++QueryCounter::$count;

                        return parent::query($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        ++QueryCounter::$count;

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
