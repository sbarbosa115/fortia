<?php

namespace App\Tests\Unit\Branding;

use App\Branding\Domain\BrandStyles;
use App\Branding\Domain\Contrast;
use PHPUnit\Framework\TestCase;

/** The readability rule of designed styles (PRD §7.16 step 3: text contrast ≥ 3:1, white/black or muted variants). */
final class ContrastTest extends TestCase
{
    public function testTheRatioIsTheWcagContrastRatio(): void
    {
        self::assertEqualsWithDelta(21.0, Contrast::ratio('#000000', '#ffffff'), 0.01, 'black on white is the maximum, 21:1');
        self::assertEqualsWithDelta(1.0, Contrast::ratio('#8249df', '#8249df'), 0.001, 'a colour on itself is 1:1');
        self::assertEqualsWithDelta(Contrast::ratio('#fff', '#777'), Contrast::ratio('#777777', '#ffffff'), 0.001, 'short hex and order do not matter');
        self::assertEqualsWithDelta(4.48, Contrast::ratio('#777777', '#ffffff'), 0.01);
    }

    public function testAColourThatAlreadyReadsIsKept(): void
    {
        self::assertSame('#1d4ed8', Contrast::readable('#1d4ed8', '#ffffff'), 'blue on white is above 3:1: keep the brand colour');
    }

    public function testATextThatDoesNotReadFallsBackToWhiteOrBlack(): void
    {
        self::assertSame('#000000', Contrast::readable('#fde68a', '#ffffff'), '§7.16: pale yellow on white → black');
        self::assertSame('#ffffff', Contrast::readable('#1e293b', '#0f172a'), '§7.16: dark slate on navy → white');
    }

    public function testAMutedTextFirstTriesAVariantOfItsOwnColour(): void
    {
        $fixed = Contrast::readable('#cbd5e1', '#ffffff', muted: true);

        self::assertNotSame('#000000', $fixed, '§7.16 "muted variants": a muted text keeps its hue when a variant reads');
        self::assertGreaterThanOrEqual(Contrast::MINIMUM, Contrast::ratio($fixed, '#ffffff'));
    }

    public function testEveryTextOfTheStylesIsCheckedAgainstItsOwnBackground(): void
    {
        $styles = BrandStyles::merge(BrandStyles::defaults(), [
            'body' => ['background' => '#ffffff', 'color' => '#eeeeee'],
            'h1' => ['color' => '#f5f5f5'],
            'a' => ['color' => '#fafafa'],
            'p' => ['color' => '#e5e7eb'],
            'button' => ['primary' => ['background' => '#facc15', 'color' => '#ffffff']],
            'input' => ['background' => '#111827', 'color' => '#1f2937', 'placeholderColor' => '#374151'],
        ]);

        $fixed = Contrast::enforce($styles);

        foreach ([['body', 'color'], ['h1', 'color'], ['a', 'color'], ['p', 'color']] as [$section, $field]) {
            self::assertGreaterThanOrEqual(3.0, Contrast::ratio($fixed[$section][$field], '#ffffff'), "$section.$field reads on the page background");
        }
        self::assertSame('#000000', $fixed['button']['primary']['color'], 'white text on a yellow button → black');
        self::assertSame('#ffffff', $fixed['input']['color'], 'dark text on a dark input → white');
        self::assertGreaterThanOrEqual(3.0, Contrast::ratio($fixed['input']['placeholderColor'], '#111827'), 'the placeholder reads on the input');
        self::assertSame('#18181b', $fixed['h2']['color'], 'a text that already reads is left alone');
    }

    public function testTheDefaultStylesAlreadyPassTheRule(): void
    {
        self::assertSame(BrandStyles::defaults(), Contrast::enforce(BrandStyles::defaults()), 'the platform defaults are readable as they are');
    }

    public function testMissingColoursAreLeftAlone(): void
    {
        self::assertSame(['h1' => ['fontSize' => '2rem']], Contrast::enforce(['h1' => ['fontSize' => '2rem']]));
    }
}
