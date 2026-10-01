<?php

namespace App\Tests\Unit\Content;

use App\Content\Domain\VideoRules;
use PHPUnit\Framework\TestCase;

/** PRD §6.22: a documentation video is a YouTube URL (1–500) in es or en. */
final class VideoRulesTest extends TestCase
{
    public function testTheUsualYoutubeLinkShapesAreAccepted(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=abcDEF12345',
            'https://youtube.com/watch?feature=share&v=abcDEF12345',
            'https://m.youtube.com/watch?v=abcDEF12345',
            'https://youtu.be/abcDEF12345?t=10',
            'https://www.youtube.com/embed/abcDEF12345',
            'https://www.youtube-nocookie.com/embed/abcDEF12345',
            'https://www.youtube.com/shorts/abcDEF12345',
            'http://www.youtube.com/watch?v=abc_DEF-345',
        ] as $url) {
            self::assertNotNull(VideoRules::youtubeId($url), "PRD §6.22: {$url} is a YouTube URL");
        }
        self::assertSame('abcDEF12345', VideoRules::youtubeId('https://youtu.be/abcDEF12345'));
    }

    public function testOtherHostsAndLinksWithoutAVideoIdAreRefused(): void
    {
        foreach ([
            'https://vimeo.com/123456',
            'https://www.youtube.com/',
            'https://www.youtube.com/watch?v=short',
            'https://youtube.com.evil.test/watch?v=abcDEF12345',
            'https://evil.test/?u=https://youtu.be/abcDEF12345',
            'ftp://youtu.be/abcDEF12345',
            'not a url',
        ] as $url) {
            self::assertNull(VideoRules::youtubeId($url), "PRD §6.22: {$url} is not a YouTube video URL");
        }
    }

    public function testOnlySpanishAndEnglishAreLanguages(): void
    {
        self::assertTrue(VideoRules::isLanguage('es'));
        self::assertTrue(VideoRules::isLanguage('en'));
        self::assertFalse(VideoRules::isLanguage('fr'), 'PRD §6.22: language es | en');
        self::assertFalse(VideoRules::isLanguage('ES'), 'PRD §8.12: the query value is es|en, lowercase');
        self::assertFalse(VideoRules::isLanguage(''));
    }
}
