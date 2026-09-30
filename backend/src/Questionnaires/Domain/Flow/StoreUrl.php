<?php

namespace App\Questionnaires\Domain\Flow;

/**
 * How the store widget's page URL is matched against the flows' store URL (PRD §8.4 GET /questionnaire/find): the
 * URL normalized (scheme and host lowercase, no query, no fragment, with or without the trailing slash, http or
 * https), falling back to the site's origin.
 */
final class StoreUrl
{
    /** @return list<string> the spellings the page's own URL may have been stored with */
    public static function candidates(string $url): array
    {
        $parts = self::parts($url);
        if (null === $parts) {
            return [];
        }
        $path = rtrim($parts['path'], '/');
        if ('' === $path) {
            return self::originCandidates($url);
        }

        $out = [];
        foreach (['https', 'http'] as $scheme) {
            $out[] = "$scheme://{$parts['host']}$path";
            $out[] = "$scheme://{$parts['host']}$path/";
        }

        return $out;
    }

    /** @return list<string> the spellings of the site's origin */
    public static function originCandidates(string $url): array
    {
        $parts = self::parts($url);
        if (null === $parts) {
            return [];
        }

        $out = [];
        foreach (['https', 'http'] as $scheme) {
            $out[] = "$scheme://{$parts['host']}";
            $out[] = "$scheme://{$parts['host']}/";
        }

        return $out;
    }

    /** @return array{host: string, path: string}|null */
    private static function parts(string $url): ?array
    {
        $url = trim($url);
        if ('' === $url || 1 === preg_match('/\s/', $url)) {
            return null;
        }
        if (1 !== preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parsed = parse_url($url);
        $host = \is_array($parsed) ? strtolower((string) ($parsed['host'] ?? '')) : '';
        if ('' === $host || !str_contains($host, '.')) {
            return null;
        }
        if (isset($parsed['port'])) {
            $host .= ':'.$parsed['port'];
        }

        return ['host' => $host, 'path' => (string) ($parsed['path'] ?? '')];
    }
}
