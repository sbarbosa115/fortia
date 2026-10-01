<?php

namespace App\Branding\Infrastructure\Llm;

use App\Branding\Application\Job\StylesJob;
use App\Branding\Domain\BrandStyles;
use App\Branding\Domain\Contrast;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Infrastructure\Llm\Fake\FakeLlmResponder;

/**
 * Offline style design (PRD §7.16 step 2), predictable from what the extractor found: the most saturated colour is
 * the brand colour (buttons and links), the lightest is the page, the darkest the text; the first font the site uses
 * that the console also offers (else Inter); the first logo candidate.
 */
final class BrandStylesResponder implements FakeLlmResponder
{
    public function supports(LlmRequest $request): bool
    {
        return StylesJob::PURPOSE === $request->purpose;
    }

    public function respond(LlmRequest $request): LlmResponse
    {
        $snapshot = \is_array($request->context['snapshot'] ?? null) ? $request->context['snapshot'] : [];
        $colors = array_values(array_filter((array) ($snapshot['colors'] ?? []), static fn (mixed $c): bool => \is_string($c) && 1 === preg_match('/^#[0-9a-f]{6}$/i', $c)));
        $fonts = array_values(array_map('strval', (array) ($snapshot['fonts'] ?? [])));
        $logos = array_values(array_map('strval', (array) ($snapshot['logo_candidates'] ?? [])));

        $brand = self::mostSaturated($colors) ?? '#8249df';
        $page = self::byLuminance($colors, true) ?? '#ffffff';
        $text = self::byLuminance($colors, false) ?? '#111827';
        if (Contrast::ratio($page, $text) < 4.5) {
            [$page, $text] = ['#ffffff', '#111827'];
        }
        $font = 'Inter';
        foreach ($fonts as $candidate) {
            if (\in_array($candidate, BrandStyles::FONTS, true)) {
                $font = $candidate;
                break;
            }
        }
        $onBrand = Contrast::luminance($brand) > 0.6 ? '#0f172a' : '#ffffff';
        $hover = Contrast::mix('#000000', $brand, 0.12);
        $muted = Contrast::mix($page, $text, 0.3);
        $text3 = static fn (string $size, string $weight, string $color): array => ['fontSize' => $size, 'fontWeight' => $weight, 'color' => $color];
        $button = static fn (string $background, string $hoverColor, string $color, string $border): array => [
            'background' => $background, 'backgroundHover' => $hoverColor, 'color' => $color, 'border' => $border,
            'padding' => '12px 24px', 'fontSize' => '1rem', 'fontWeight' => '600', 'borderRadius' => '8px',
        ];

        return LlmResponse::json([
            'styles' => [
                'font' => ['family' => $font],
                'body' => ['background' => $page, 'color' => $text],
                'h1' => $text3('2rem', '700', $text),
                'h2' => $text3('1.5rem', '600', $text),
                'h3' => $text3('1.25rem', '600', $text),
                'p' => $text3('1rem', '400', $muted),
                'label' => ['fontSize' => '0.875rem', 'fontWeight' => '600', 'color' => $text, 'textTransform' => 'none', 'letterSpacing' => '0'],
                'a' => ['color' => $brand],
                'button' => [
                    'primary' => $button($brand, $hover, $onBrand, 'none'),
                    'secondary' => $button($page, Contrast::mix($text, $page, 0.06), $text, '1px solid '.Contrast::mix($text, $page, 0.2)),
                ],
                'input' => [
                    'background' => '#ffffff', 'color' => $text, 'placeholderColor' => $muted, 'border' => '1px solid '.Contrast::mix($text, $page, 0.2),
                    'borderFocus' => '2px solid '.$brand, 'borderRadius' => '8px', 'padding' => '12px 14px', 'fontSize' => '1rem',
                ],
            ],
            'logo_url' => $logos[0] ?? null,
        ]);
    }

    /** @param list<string> $colors */
    private static function mostSaturated(array $colors): ?string
    {
        $best = null;
        $bestSaturation = 0.15;
        foreach ($colors as $color) {
            [$r, $g, $b] = array_map(static fn (string $h): float => hexdec($h) / 255, str_split(substr($color, 1), 2));
            $saturation = max($r, $g, $b) - min($r, $g, $b);
            if ($saturation > $bestSaturation) {
                [$best, $bestSaturation] = [$color, $saturation];
            }
        }

        return $best;
    }

    /** @param list<string> $colors */
    private static function byLuminance(array $colors, bool $lightest): ?string
    {
        if ([] === $colors) {
            return null;
        }
        usort($colors, static fn (string $a, string $b): int => Contrast::luminance($a) <=> Contrast::luminance($b));

        return $lightest ? $colors[\count($colors) - 1] : $colors[0];
    }
}
