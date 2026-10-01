<?php

namespace App\Branding\Domain;

/**
 * The readability rule of designed styles (PRD §7.16 step 3): every text colour keeps a contrast of at least 3:1
 * against the background it sits on. A colour that fails is replaced by white or black (whichever reads better on
 * that background); a muted text (paragraphs, labels, placeholders) first tries a muted variant of its own colour,
 * pushed towards white or black only as far as it needs, so it keeps the brand's hue.
 */
final class Contrast
{
    public const MINIMUM = 3.0;

    public const WHITE = '#ffffff';
    public const BLACK = '#000000';

    /**
     * Which text sits on which background: [text path, background path, muted?].
     *
     * @var list<array{0: list<string>, 1: list<string>, 2: bool}>
     */
    private const PAIRS = [
        [['body', 'color'], ['body', 'background'], false],
        [['h1', 'color'], ['body', 'background'], false],
        [['h2', 'color'], ['body', 'background'], false],
        [['h3', 'color'], ['body', 'background'], false],
        [['a', 'color'], ['body', 'background'], false],
        [['p', 'color'], ['body', 'background'], true],
        [['label', 'color'], ['body', 'background'], true],
        [['button', 'primary', 'color'], ['button', 'primary', 'background'], false],
        [['button', 'secondary', 'color'], ['button', 'secondary', 'background'], false],
        [['input', 'color'], ['input', 'background'], false],
        [['input', 'placeholderColor'], ['input', 'background'], true],
    ];

    /** The WCAG contrast ratio of two hex colours (alpha ignored), from 1 to 21. */
    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** The relative luminance of a hex colour (0 = black, 1 = white). */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * A text colour that reads on $background: $color itself when it already does, else (muted) the closest variant
     * of it towards white or black, else white or black.
     */
    public static function readable(string $color, string $background, bool $muted = false): string
    {
        if (self::ratio($color, $background) >= self::MINIMUM) {
            return $color;
        }
        $base = self::ratio(self::WHITE, $background) >= self::ratio(self::BLACK, $background) ? self::WHITE : self::BLACK;
        if ($muted) {
            foreach ([0.25, 0.5, 0.75] as $weight) {
                $variant = self::mix($base, $color, $weight);
                if (self::ratio($variant, $background) >= self::MINIMUM) {
                    return $variant;
                }
            }
        }

        return $base;
    }

    /**
     * Applies the rule to every text colour of a full set of styles (PRD §6.18 shape). A missing text or background
     * colour is left alone.
     *
     * @param array<string, mixed> $styles
     *
     * @return array<string, mixed>
     */
    public static function enforce(array $styles): array
    {
        foreach (self::PAIRS as [$textPath, $backgroundPath, $muted]) {
            $text = self::at($styles, $textPath);
            $background = self::at($styles, $backgroundPath);
            if (!StylesShape::isColor($text) || !StylesShape::isColor($background)) {
                continue;
            }
            $readable = self::readable($text, $background, $muted);
            if ($readable !== $text) {
                $styles = self::put($styles, $textPath, $readable);
            }
        }

        return $styles;
    }

    /** $a mixed into $b by $weight (0 = $b, 1 = $a), as #rrggbb. */
    public static function mix(string $a, string $b, float $weight): string
    {
        $ca = self::rgb($a);
        $cb = self::rgb($b);
        $mixed = array_map(static fn (int $x, int $y): int => (int) round($x * $weight + $y * (1 - $weight)), $ca, $cb);

        return \sprintf('#%02x%02x%02x', ...$mixed);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $digits = ltrim(trim($hex), '#');
        if (\strlen($digits) <= 4) {
            $digits = implode('', array_map(static fn (string $d): string => $d.$d, str_split($digits)));
        }

        return [(int) hexdec(substr($digits, 0, 2)), (int) hexdec(substr($digits, 2, 2)), (int) hexdec(substr($digits, 4, 2))];
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string>         $path
     */
    private static function at(array $source, array $path): mixed
    {
        $value = $source;
        foreach ($path as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string>         $path
     *
     * @return array<string, mixed>
     */
    private static function put(array $source, array $path, string $value): array
    {
        $key = array_shift($path);
        if (null === $key) {
            return $source;
        }
        if ([] === $path) {
            $source[$key] = $value;

            return $source;
        }
        $child = \is_array($source[$key] ?? null) ? $source[$key] : [];
        $source[$key] = self::put($child, $path, $value);

        return $source;
    }
}
