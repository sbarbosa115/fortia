<?php

namespace App\Branding\Domain;

/**
 * The platform's default styles (PRD §6.18 "a set of default styles defined by the platform") and the deep merge of
 * §7.16: a partial set sent without a website change goes over the stored styles, or over these defaults.
 *
 * The defaults are the respondent app's own look (Appendix A.2): warm off-white page, zinc-900 text and buttons, a
 * terracotta link colour, Montserrat. Saving them leaves the respondent screens as they look without a brand.
 */
final class BrandStyles
{
    /** The fonts the console offers (PRD §10.13). */
    public const FONTS = ['Inter', 'Roboto', 'Poppins', 'Montserrat', 'Playfair Display', 'Lora'];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $text = '#18181b';
        $muted = '#52525b';
        $page = '#f8f6f2';
        $border = '#d4d4d8';

        return [
            'font' => ['family' => 'Montserrat', 'url' => self::googleFontUrl('Montserrat')],
            'body' => ['background' => $page, 'color' => $text],
            'h1' => ['fontSize' => '2rem', 'fontWeight' => 700, 'color' => $text],
            'h2' => ['fontSize' => '1.5rem', 'fontWeight' => 600, 'color' => $text],
            'h3' => ['fontSize' => '1.25rem', 'fontWeight' => 600, 'color' => $text],
            'p' => ['fontSize' => '1rem', 'fontWeight' => 400, 'color' => $muted],
            'label' => ['fontSize' => '0.875rem', 'fontWeight' => 600, 'color' => $text, 'textTransform' => 'none', 'letterSpacing' => '0'],
            'a' => ['color' => '#c45a3d'],
            'button' => [
                'primary' => [
                    'background' => $text, 'backgroundHover' => '#3f3f46', 'color' => '#ffffff', 'border' => 'none',
                    'padding' => '12px 24px', 'fontSize' => '1rem', 'fontWeight' => 600, 'borderRadius' => '999px',
                ],
                'secondary' => [
                    'background' => '#ffffff', 'backgroundHover' => '#f4f4f5', 'color' => $text, 'border' => '1px solid '.$border,
                    'padding' => '12px 24px', 'fontSize' => '1rem', 'fontWeight' => 600, 'borderRadius' => '999px',
                ],
            ],
            'input' => [
                'background' => '#ffffff', 'color' => $text, 'placeholderColor' => '#71717a', 'border' => '1px solid '.$border,
                'borderFocus' => '2px solid '.$text, 'borderRadius' => '0.5rem', 'padding' => '12px 14px', 'fontSize' => '1rem',
            ],
        ];
    }

    /** The Google Fonts stylesheet of a font family (the only font provider allowed, PRD §9.15). */
    public static function googleFontUrl(string $family): string
    {
        return 'https://fonts.googleapis.com/css2?family='.str_replace(' ', '+', trim($family)).':wght@400;500;600;700&display=swap';
    }

    /**
     * $partial deep-merged over $base: objects merge key by key, any other value replaces, and null removes the key.
     *
     * @param array<mixed> $base
     * @param array<mixed> $partial
     *
     * @return array<mixed>
     */
    public static function merge(array $base, array $partial): array
    {
        foreach ($partial as $key => $value) {
            if (null === $value) {
                unset($base[$key]);
            } elseif (\is_array($value) && !array_is_list($value) && \is_array($base[$key] ?? null)) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
