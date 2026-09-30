<?php

namespace App\Tests\Unit\Questionnaires;

use App\Questionnaires\Domain\Flow\CopyNaming;
use App\Questionnaires\Domain\Flow\Slugs;
use App\Questionnaires\Domain\Flow\StoreUrl;
use PHPUnit\Framework\TestCase;

/** PRD §7.5 "Copy" naming (D23: in the account's language), generated slugs, and §8.4 find's URL matching. */
final class NamingAndStoreUrlTest extends TestCase
{
    public function testACopyIsTitledInTheAccountsLanguage(): void
    {
        self::assertSame('(copia) Survey', CopyNaming::title('Survey', 'es-CO', static fn (): bool => false));
        self::assertSame('(copy) Survey', CopyNaming::title('Survey', 'en-US', static fn (): bool => false));
    }

    public function testASecondCopyIsNumbered(): void
    {
        $taken = ['(copia) Survey', '(copia - 2) Survey'];

        self::assertSame('(copia - 3) Survey', CopyNaming::title('Survey', 'es-CO', static fn (string $t): bool => \in_array($t, $taken, true)));
    }

    public function testTheCopysSlugIsTheBasePlusCopia(): void
    {
        self::assertSame('survey-copia', CopyNaming::slug('survey', 'es-CO', static fn (): bool => false));
        self::assertSame('survey-copy-2', CopyNaming::slug('survey', 'en-US', static fn (string $s): bool => 'survey-copy' === $s));
        self::assertSame(100, \strlen(CopyNaming::slug(str_repeat('a', 100), 'es-CO', static fn (): bool => false)), 'a slug has at most 100 characters');
    }

    public function testAGeneratedSlugComesFromTheTitleAndAvoidsTakenOnes(): void
    {
        self::assertSame('encuesta-de-cafe', Slugs::fromTitle('Encuesta de Café!', static fn (): bool => false));
        self::assertSame('encuesta-2', Slugs::fromTitle('Encuesta', static fn (string $s): bool => 'encuesta' === $s));
        self::assertSame('questionnaire', Slugs::fromTitle('¿?', static fn (): bool => false), 'a title without letters still gets a slug');
    }

    public function testAStoreUrlMatchesWithoutQueryFragmentOrTrailingSlash(): void
    {
        $candidates = StoreUrl::candidates('HTTPS://Shop.Example.com/collections/all/?utm=1#top');

        self::assertContains('https://shop.example.com/collections/all', $candidates);
        self::assertContains('https://shop.example.com/collections/all/', $candidates);
        self::assertContains('http://shop.example.com/collections/all', $candidates, 'the scheme does not matter');
        self::assertNotContains('https://shop.example.com', $candidates, 'the origin is only the fallback');
    }

    public function testTheFallbackIsTheSitesOrigin(): void
    {
        $origins = StoreUrl::originCandidates('shop.example.com/products/x');

        self::assertContains('https://shop.example.com', $origins, 'a URL without a scheme is read as https');
        self::assertContains('https://shop.example.com/', $origins);
        self::assertSame([], StoreUrl::originCandidates('not a url'));
    }
}
