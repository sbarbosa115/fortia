<?php

namespace App\Branding\Domain;

/**
 * The shape of an account's styles (PRD §6.18, camelCase) and the value rules the respondent app applies (§9.15):
 * hex colours of 3, 4, 6 or 8 digits; lengths in px, rem, em or %; a font name of ≤ 60 characters starting with a
 * letter; a font stylesheet only from Google Fonts; an http(s) logo URL.
 *
 * violations() checks a partial set sent by the console (unknown keys and bad values are refused); sanitize() keeps
 * only the valid part of a set that comes from elsewhere (the language model, an old record).
 */
final class StylesShape
{
    private const COLOR = 'color';
    private const LENGTH = 'length';
    private const SIGNED_LENGTH = 'signedLength';
    private const SPACING = 'spacing';
    private const WEIGHT = 'weight';
    private const BORDER = 'border';
    private const BORDER_OR_COLOR = 'borderOrColor';
    private const TRANSFORM = 'transform';
    private const FONT_NAME = 'fontName';
    private const FONT_URL = 'fontUrl';
    private const IMAGE_URL = 'imageUrl';

    private const TEXT = ['fontSize' => self::LENGTH, 'fontWeight' => self::WEIGHT, 'color' => self::COLOR];
    private const BUTTON = [
        'background' => self::COLOR, 'backgroundHover' => self::COLOR, 'color' => self::COLOR, 'border' => self::BORDER,
        'padding' => self::SPACING, 'fontSize' => self::LENGTH, 'fontWeight' => self::WEIGHT, 'borderRadius' => self::LENGTH,
    ];

    /** @var array<string, mixed> */
    private const SHAPE = [
        'logoUrl' => self::IMAGE_URL,
        'font' => ['family' => self::FONT_NAME, 'url' => self::FONT_URL],
        'body' => ['background' => self::COLOR, 'color' => self::COLOR],
        'h1' => self::TEXT,
        'h2' => self::TEXT,
        'h3' => self::TEXT,
        'p' => self::TEXT,
        'label' => self::TEXT + ['textTransform' => self::TRANSFORM, 'letterSpacing' => self::SIGNED_LENGTH],
        'a' => ['color' => self::COLOR],
        'button' => ['primary' => self::BUTTON, 'secondary' => self::BUTTON],
        'input' => [
            'background' => self::COLOR, 'color' => self::COLOR, 'placeholderColor' => self::COLOR, 'border' => self::BORDER,
            'borderFocus' => self::BORDER_OR_COLOR, 'borderRadius' => self::LENGTH, 'padding' => self::SPACING, 'fontSize' => self::LENGTH,
        ],
    ];

    private const HEX = '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';
    private const LENGTH_PATTERN = '(?:0|\d+(?:\.\d+)?(?:px|rem|em|%))';
    private const FONT_NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9 _-]*$/';
    private const FONT_URL_PATTERN = '/^https:\/\/fonts\.googleapis\.com\/css2?\?[^\s"\'<>()]+$/';

    /** The messages of each rule, in the console's language-neutral English (PRD §8.1 VALIDATION_ERROR). */
    private const MESSAGES = [
        self::COLOR => 'Use a hex color such as #1A2B3C.',
        self::LENGTH => 'Use a length in px, rem, em or %.',
        self::SIGNED_LENGTH => 'Use a length in px, rem, em or %.',
        self::SPACING => 'Use one to four lengths in px, rem, em or %.',
        self::WEIGHT => 'Use a font weight from 100 to 900, "normal" or "bold".',
        self::BORDER => 'Use "none" or a border such as "1px solid #D4D4D8".',
        self::BORDER_OR_COLOR => 'Use a hex color or a border such as "2px solid #8249DF".',
        self::TRANSFORM => 'Use none, uppercase, lowercase or capitalize.',
        self::FONT_NAME => 'Use a font name of up to 60 letters, digits, spaces, dashes or underscores.',
        self::FONT_URL => 'Only Google Fonts stylesheets (https://fonts.googleapis.com/css2?…) are allowed.',
        self::IMAGE_URL => 'Use an http or https URL of up to 2048 characters.',
    ];

    public static function isColor(mixed $value): bool
    {
        return \is_string($value) && 1 === preg_match(self::HEX, $value);
    }

    /**
     * What is wrong with a partial set of styles, as [field path, message] pairs (empty = valid). A null leaf is
     * allowed: it removes that value.
     *
     * @param array<mixed> $styles
     *
     * @return list<array{field: string, message: string}>
     */
    public static function violations(array $styles, string $prefix = 'styles'): array
    {
        return self::check($styles, self::SHAPE, $prefix);
    }

    /**
     * Only the valid part of a set of styles: unknown keys, bad values and empty sections are dropped.
     *
     * @param array<mixed> $styles
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $styles): array
    {
        return self::clean($styles, self::SHAPE);
    }

    /**
     * @param array<mixed>         $value
     * @param array<string, mixed> $shape
     *
     * @return list<array{field: string, message: string}>
     */
    private static function check(array $value, array $shape, string $path): array
    {
        $violations = [];
        foreach ($value as $key => $child) {
            $field = $path.'.'.$key;
            $rule = \is_string($key) ? ($shape[$key] ?? null) : null;
            if (null === $rule) {
                $violations[] = ['field' => $field, 'message' => 'This field was not expected.'];
            } elseif (\is_array($rule)) {
                if (null === $child) {
                    continue;
                }
                if (!\is_array($child) || ([] !== $child && array_is_list($child))) {
                    $violations[] = ['field' => $field, 'message' => 'This value should be an object.'];
                } else {
                    $violations = [...$violations, ...self::check($child, $rule, $field)];
                }
            } elseif (null !== $child && !self::valid((string) $rule, $child)) {
                $violations[] = ['field' => $field, 'message' => self::MESSAGES[$rule]];
            }
        }

        return $violations;
    }

    /**
     * @param array<mixed>         $value
     * @param array<string, mixed> $shape
     *
     * @return array<string, mixed>
     */
    private static function clean(array $value, array $shape): array
    {
        $clean = [];
        foreach ($shape as $key => $rule) {
            if (!\array_key_exists($key, $value)) {
                continue;
            }
            $child = $value[$key];
            if (\is_array($rule)) {
                $nested = \is_array($child) ? self::clean($child, $rule) : [];
                if ([] !== $nested) {
                    $clean[$key] = $nested;
                }
            } elseif (self::valid((string) $rule, $child)) {
                $clean[$key] = \is_string($child) ? trim($child) : $child;
            }
        }

        return $clean;
    }

    private static function valid(string $rule, mixed $value): bool
    {
        if (self::WEIGHT === $rule && \is_int($value)) {
            return $value >= 100 && $value <= 900 && 0 === $value % 100;
        }
        if (!\is_string($value)) {
            return false;
        }
        $value = trim($value);
        $length = self::LENGTH_PATTERN;

        return match ($rule) {
            self::COLOR => self::isColor($value),
            self::LENGTH => 1 === preg_match('/^'.$length.'$/', $value),
            self::SIGNED_LENGTH => 1 === preg_match('/^-?'.$length.'$/', $value),
            self::SPACING => 1 === preg_match('/^'.$length.'(?: '.$length.'){0,3}$/', $value),
            self::WEIGHT => 1 === preg_match('/^(?:[1-9]00|normal|bold)$/', $value),
            self::BORDER => self::isBorder($value),
            self::BORDER_OR_COLOR => self::isColor($value) || self::isBorder($value),
            self::TRANSFORM => \in_array($value, ['none', 'uppercase', 'lowercase', 'capitalize'], true),
            self::FONT_NAME => \strlen($value) <= 60 && 1 === preg_match(self::FONT_NAME_PATTERN, $value),
            self::FONT_URL => 1 === preg_match(self::FONT_URL_PATTERN, $value),
            self::IMAGE_URL => \strlen($value) <= 2048 && 1 === preg_match('#^https?://[^\s"\'<>]+$#i', $value),
            default => false,
        };
    }

    private static function isBorder(string $value): bool
    {
        return 'none' === $value || '0' === $value
            || 1 === preg_match('/^'.self::LENGTH_PATTERN.' (?:solid|dashed|dotted|double) #(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value);
    }
}
