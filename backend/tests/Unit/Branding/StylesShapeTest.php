<?php

namespace App\Tests\Unit\Branding;

use App\Branding\Domain\BrandStyles;
use App\Branding\Domain\LogoChoice;
use App\Branding\Domain\StylesShape;
use PHPUnit\Framework\TestCase;

/** The styles shape (PRD §6.18), the value rules of §9.15, the deep merge of §7.16 and the logo choice (step 4). */
final class StylesShapeTest extends TestCase
{
    public function testAValidPartialSetHasNoViolations(): void
    {
        $partial = [
            'logoUrl' => 'https://acme.test/logo.svg',
            'font' => ['family' => 'Playfair Display', 'url' => BrandStyles::googleFontUrl('Playfair Display')],
            'button' => ['primary' => ['background' => '#8249DF', 'padding' => '10px 20px', 'fontWeight' => 600, 'border' => 'none']],
            'label' => ['letterSpacing' => '-0.01em', 'textTransform' => 'uppercase'],
            'input' => ['borderFocus' => '#8249df'],
        ];

        self::assertSame([], StylesShape::violations($partial));
        self::assertSame([], StylesShape::violations(BrandStyles::defaults()), 'the defaults are a valid set');
    }

    public function testUnknownKeysAndBadValuesAreReportedByPath(): void
    {
        $violations = StylesShape::violations([
            'body' => ['background' => 'red', 'shadow' => '1px'],
            'font' => ['family' => '1Font', 'url' => 'https://evil.test/font.css'],
            'button' => ['primary' => ['borderRadius' => '12']],
            'logoUrl' => 'javascript:alert(1)',
            'extra' => true,
            'h1' => 'big',
        ]);

        self::assertSame([
            'styles.body.background', 'styles.body.shadow', 'styles.font.family', 'styles.font.url',
            'styles.button.primary.borderRadius', 'styles.logoUrl', 'styles.extra', 'styles.h1',
        ], array_column($violations, 'field'), '§8.5 "styles? (partial, camelCase, validated)": every bad field is named');
    }

    public function testNullRemovesAValueAndIsAllowed(): void
    {
        self::assertSame([], StylesShape::violations(['logoUrl' => null, 'a' => null]));
        self::assertArrayNotHasKey('logoUrl', BrandStyles::merge(['logoUrl' => 'https://a.test/l.png', 'a' => ['color' => '#000']], ['logoUrl' => null]));
    }

    public function testSanitizeKeepsOnlyTheValidPart(): void
    {
        $clean = StylesShape::sanitize([
            'body' => ['background' => '#FFFFFF', 'color' => 'black'],
            'font' => ['family' => 'Inter; } body { display:none', 'url' => 'https://fonts.googleapis.com/css2?family=Inter'],
            'a' => ['color' => 'url(x)'],
            'script' => 'alert',
        ]);

        self::assertSame([
            'font' => ['url' => 'https://fonts.googleapis.com/css2?family=Inter'],
            'body' => ['background' => '#FFFFFF'],
        ], $clean, '§14 sanitize all CSS from the LLM: invalid values, injected CSS and unknown keys are dropped');
    }

    public function testThePartialIsDeepMergedOverTheBase(): void
    {
        $merged = BrandStyles::merge(BrandStyles::defaults(), ['button' => ['primary' => ['background' => '#8249df']], 'logoUrl' => 'https://acme.test/l.png']);

        self::assertSame('#8249df', $merged['button']['primary']['background'], '§7.16: the partial wins');
        self::assertSame('#ffffff', $merged['button']['primary']['color'], '§7.16 deep merge: the sibling values are kept');
        self::assertSame(BrandStyles::defaults()['button']['secondary'], $merged['button']['secondary']);
        self::assertSame('https://acme.test/l.png', $merged['logoUrl']);
    }

    public function testTheLogoIsOneOfTheCandidates(): void
    {
        $candidates = ['https://acme.test/hero.jpg', 'https://acme.test/img/acme-logo.svg', 'https://acme.test/favicon.png'];

        self::assertSame('https://acme.test/favicon.png', LogoChoice::pick('https://acme.test/favicon.png', $candidates, null), "the model's pick when it is a candidate");
        self::assertSame('https://acme.test/img/acme-logo.svg', LogoChoice::pick('https://elsewhere.test/x.png', $candidates, null), '§7.16 step 4: never a URL that was not found on the page');
        self::assertSame('https://acme.test/hero.jpg', LogoChoice::pick(null, ['https://acme.test/hero.jpg'], null));
        self::assertSame('https://old.test/logo.png', LogoChoice::pick(null, [], 'https://old.test/logo.png'), 'no candidates: keep the logo the account had');
    }
}
