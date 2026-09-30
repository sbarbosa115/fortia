<?php

namespace App\Tests\Support;

use App\Shared\Domain\Clock;

/** The clock of the test container: real time until a test sets it. */
final class TestClock implements Clock
{
    private ?\DateTimeImmutable $now = null;

    public function now(): \DateTimeImmutable
    {
        return $this->now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function today(): string
    {
        return $this->now()->format('Y-m-d');
    }

    public function set(string $datetime): void
    {
        $this->now = new \DateTimeImmutable($datetime, new \DateTimeZone('UTC'));
    }

    public function reset(): void
    {
        $this->now = null;
    }
}
