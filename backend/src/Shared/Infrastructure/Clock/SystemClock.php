<?php

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Domain\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function today(): string
    {
        return $this->now()->format('Y-m-d');
    }
}
