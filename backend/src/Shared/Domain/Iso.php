<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/** Dates as the API writes them: ISO-8601 UTC with "Z" for instants, YYYY-MM-DD for calendar dates (PRD §6). */
final class Iso
{
    public const DATE = '/^\d{4}-\d{2}-\d{2}$/';

    public static function datetime(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    public static function isDate(string $value): bool
    {
        if (1 !== preg_match(self::DATE, $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }

    /** Adds calendar months, clamping the day to the target month's length (PRD §7.3: Jan 31 + 1 month = Feb 28). */
    public static function addMonths(string $date, int $months): string
    {
        $start = new \DateTimeImmutable($date.'T00:00:00Z');
        $year = (int) $start->format('Y');
        $month = (int) $start->format('n') + $months;
        $year += intdiv($month - 1, 12);
        $month = (($month - 1) % 12) + 1;
        $lastDay = (int) (new \DateTimeImmutable(\sprintf('%04d-%02d-01T00:00:00Z', $year, $month)))->format('t');

        return \sprintf('%04d-%02d-%02d', $year, $month, min((int) $start->format('j'), $lastDay));
    }
}
