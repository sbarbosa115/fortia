<?php

namespace App\Generation\Domain;

/**
 * POST /questionnaire/linkedin's rules (PRD §8.4, §7.18): the URL of a public profile
 * (`https?://([a-z]{2,3}\.)?(www\.)?linkedin.com/in/...`) and a language, `en` or `es` (any other value becomes es).
 */
final class LinkedinRequest
{
    public const URL_PATTERN = '#^https?://([a-z]{2,3}\.)?(www\.)?linkedin\.com/in/[^/?\#\s]+/?([?\#]\S*)?$#i';

    public static function isProfileUrl(string $url): bool
    {
        return 1 === preg_match(self::URL_PATTERN, trim($url));
    }

    /** The profile's handle, the segment after /in/. */
    public static function handle(string $url): string
    {
        $path = (string) parse_url(trim($url), \PHP_URL_PATH);

        return rawurldecode(trim((string) preg_replace('#^/in/#i', '', $path), '/'));
    }

    public static function language(mixed $language): string
    {
        return 'en' === $language ? 'en' : 'es';
    }
}
