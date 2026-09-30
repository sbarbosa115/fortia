<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Ids;
use App\Shared\Domain\Text;
use PHPUnit\Framework\TestCase;

final class IdsAndTextTest extends TestCase
{
    public function testIdsHaveThePrdFormats(): void
    {
        self::assertTrue(Ids::isUuid4(Ids::uuid4()));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9]{8}$/', Ids::customerId(), 'PRD §6.1: 8 random alphanumerics');
        self::assertMatchesRegularExpression('/^job_[0-9A-Z]{26}$/', Ids::jobId(), 'PRD §6.17: job_ + a ULID');
    }

    public function testJobIdsSortByCreationTime(): void
    {
        $earlier = Ids::jobId(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $later = Ids::jobId(new \DateTimeImmutable('2026-01-01T00:00:01Z'));

        self::assertLessThan(0, strcmp($earlier, $later));
    }

    public function testFoldRemovesAccentsCaseAndExtraSpaces(): void
    {
        self::assertSame('jose perez', Text::fold('  José   PÉREZ '), 'PRD §6.13: member names are normalized this way');
    }

    public function testSlugify(): void
    {
        self::assertSame('diagnostico-de-madurez-ia', Text::slugify('Diagnóstico de Madurez IA!'));
        self::assertTrue(Text::isSlug('a-b-1'));
        self::assertFalse(Text::isSlug('A-b'));
        self::assertFalse(Text::isSlug('a--b'));
    }

    public function testNormalizePhoneKeepsDigitsAndALeadingPlus(): void
    {
        self::assertSame('+573001234567', Text::normalizePhone(' +57 (300) 123-4567 '));
    }

    public function testEveryWordOfTheQueryMustAppearIgnoringAccents(): void
    {
        self::assertTrue(Text::matchesAllWords('Encuesta de Satisfacción', 'satisfaccion encuesta'));
        self::assertFalse(Text::matchesAllWords('Encuesta de Satisfacción', 'satisfaccion clientes'));
    }

    public function testEscapeLikeMakesWildcardsLiteral(): void
    {
        self::assertSame('50\\%\\_off', Text::escapeLike('50%_off'));
    }
}
