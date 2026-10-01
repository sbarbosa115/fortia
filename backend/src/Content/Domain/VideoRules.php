<?php

namespace App\Content\Domain;

/**
 * Rules of a documentation video (PRD §6.22): the URL is a YouTube video link and the language is es or en.
 */
final class VideoRules
{
    public const LANGUAGES = ['es', 'en'];

    private const HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be', 'www.youtu.be'];
    private const VIDEO_ID = '/^[A-Za-z0-9_-]{11}$/';

    public static function isLanguage(string $value): bool
    {
        return \in_array($value, self::LANGUAGES, true);
    }

    /**
     * The 11-character video id of a YouTube link (watch?v=, youtu.be/, embed/, shorts/), or null when the URL is not
     * one: another host, no id, or not http(s).
     */
    public static function youtubeId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (false === $parts || !\in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        if (!\in_array($host, self::HOSTS, true)) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (str_ends_with($host, 'youtu.be')) {
            $candidate = ltrim($path, '/');
        } elseif ('/watch' === rtrim($path, '/')) {
            parse_str($parts['query'] ?? '', $query);
            $candidate = \is_string($query['v'] ?? null) ? $query['v'] : '';
        } elseif (1 === preg_match('#^/(embed|shorts|live)/([^/]+)/?$#', $path, $match)) {
            $candidate = $match[2];
        } else {
            return null;
        }

        return 1 === preg_match(self::VIDEO_ID, $candidate) ? $candidate : null;
    }
}
