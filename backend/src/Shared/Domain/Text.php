<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Text rules the PRD repeats in several places: normalized member names (§6.13), accent- and case-insensitive
 * comparison (audience area/role, search), slugs (§6.6) and phone numbers.
 */
final class Text
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** Lowercase, without accents, trimmed, with single spaces: "  José   PÉREZ " → "jose perez". */
    public static function fold(string $value): string
    {
        $value = self::withoutAccents($value);
        $value = mb_strtolower($value, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public static function withoutAccents(string $value): string
    {
        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
        if (false === $normalized) {
            return $value;
        }

        return (string) preg_replace('/\p{Mn}+/u', '', $normalized);
    }

    /** "Café con Leche!" → "cafe-con-leche". Empty when nothing is left. */
    public static function slugify(string $value, int $maxLength = 100): string
    {
        $slug = self::fold($value);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        if (\strlen($slug) > $maxLength) {
            $slug = rtrim(substr($slug, 0, $maxLength), '-');
        }

        return $slug;
    }

    public static function isSlug(string $value): bool
    {
        return '' !== $value && \strlen($value) <= 100 && 1 === preg_match(self::SLUG_PATTERN, $value);
    }

    /** Digits only, with an optional leading "+": " +57 (300) 123-4567 " → "+573001234567". */
    public static function normalizePhone(string $value): string
    {
        $value = trim($value);
        $plus = str_starts_with($value, '+') ? '+' : '';

        return $plus.preg_replace('/\D+/', '', $value);
    }

    /**
     * The words of a search query, folded: every one must appear in the searched text (PRD §8.1).
     *
     * @return list<string>
     */
    public static function searchWords(string $query, int $maxLength = 200): array
    {
        $query = mb_substr(trim($query), 0, $maxLength);
        $words = preg_split('/\s+/u', self::fold($query), -1, \PREG_SPLIT_NO_EMPTY);

        return false === $words ? [] : array_values($words);
    }

    /** Whether every word of $query appears in $haystack, ignoring case and accents. */
    public static function matchesAllWords(string $haystack, string $query): bool
    {
        $folded = self::fold($haystack);
        foreach (self::searchWords($query) as $word) {
            if (!str_contains($folded, $word)) {
                return false;
            }
        }

        return true;
    }

    /** Escapes % and _ so a user's text matches literally in a LIKE (steps/04 §4.1). */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
