<?php

namespace App\Questionnaires\Domain\Flow;

/**
 * The title and slug of a copy (PRD §7.5): "(copia) X", then "(copia - N) X"; slug "<base>-copia[-N]". The word
 * follows the account's language (D23: the prefixes were hard-coded in Spanish): "copia" in Spanish, "copy" in
 * English.
 */
final class CopyNaming
{
    public static function word(string $language): string
    {
        return str_starts_with(strtolower($language), 'en') ? 'copy' : 'copia';
    }

    /** @param callable(string): bool $titleExists */
    public static function title(string $title, string $language, callable $titleExists): string
    {
        $word = self::word($language);
        $candidate = "($word) $title";
        for ($n = 2; $titleExists($candidate); ++$n) {
            $candidate = "($word - $n) $title";
        }

        return $candidate;
    }

    /** @param callable(string): bool $slugExists */
    public static function slug(string $baseSlug, string $language, callable $slugExists): string
    {
        return Slugs::available($baseSlug.'-'.self::word($language), $slugExists);
    }
}
