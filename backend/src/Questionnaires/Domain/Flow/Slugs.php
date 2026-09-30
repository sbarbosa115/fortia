<?php

namespace App\Questionnaires\Domain\Flow;

use App\Shared\Domain\Text;

/**
 * Flow slugs (PRD §6.6: `^[a-z0-9]+(-[a-z0-9]+)*$`, 1–100, unique across the whole system). An empty slug is
 * generated from the title (§10.5 "Leave empty to generate it from the title"); a taken one gets "-2", "-3"….
 */
final class Slugs
{
    public const MAX_LENGTH = 100;

    /** @param callable(string): bool $slugExists */
    public static function fromTitle(string $title, callable $slugExists): string
    {
        $base = Text::slugify($title, self::MAX_LENGTH);

        return self::available('' === $base ? 'questionnaire' : $base, $slugExists);
    }

    /**
     * The first of $base, $base-2, $base-3… that is free, each cut to 100 characters.
     *
     * @param callable(string): bool $slugExists
     */
    public static function available(string $base, callable $slugExists): string
    {
        $candidate = self::fit($base, '');
        for ($n = 2; $slugExists($candidate); ++$n) {
            $candidate = self::fit($base, '-'.$n);
        }

        return $candidate;
    }

    private static function fit(string $base, string $suffix): string
    {
        $room = self::MAX_LENGTH - \strlen($suffix);

        return rtrim(substr($base, 0, $room), '-').$suffix;
    }
}
