<?php

namespace App\Shared\Domain;

/**
 * "Now", always in UTC. Rules that depend on the date (plan windows, due dates, reminders, overdue in UTC−12) read
 * it from here so tests can move time (tests/Support/TestClock).
 */
interface Clock
{
    public function now(): \DateTimeImmutable;

    /** Today's calendar date in UTC, as YYYY-MM-DD. */
    public function today(): string;
}
