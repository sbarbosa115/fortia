<?php

namespace App\Branding\Infrastructure\Extractor;

use App\Branding\Application\Port\BrandExtractor;
use App\Branding\Application\Port\BrandSnapshot;
use App\Branding\Domain\Error\WebsiteUnreachable;

/**
 * Offline website reading (BRAND_EXTRACTOR=fake, dev and tests): the same host always gives the same palette and
 * fonts, so the styles job runs without the network. A host containing "unreachable" fails like a site that does not
 * answer. It finds no logo candidates (a made-up logo URL would show as a broken image), so the account keeps its
 * logo. Tests can script the next answer with willReturn() / willFail().
 */
final class FakeBrandExtractor implements BrandExtractor
{
    /** @var list<array{colors: list<string>, fonts: list<string>}> */
    private const PALETTES = [
        ['colors' => ['#ffffff', '#0f172a', '#2563eb', '#e2e8f0'], 'fonts' => ['Inter', 'Helvetica']],
        ['colors' => ['#fffbf5', '#1c1917', '#c2410c', '#fde68a'], 'fonts' => ['Lora', 'Georgia']],
        ['colors' => ['#f8fafc', '#111827', '#059669', '#d1fae5'], 'fonts' => ['Poppins', 'Arial']],
        ['colors' => ['#ffffff', '#1f2937', '#db2777', '#fce7f3'], 'fonts' => ['Montserrat', 'Verdana']],
        ['colors' => ['#fafaf9', '#292524', '#7c3aed', '#ede9fe'], 'fonts' => ['Playfair Display', 'Roboto']],
    ];

    private ?BrandSnapshot $next = null;
    private bool $fail = false;

    public function extract(string $website): BrandSnapshot
    {
        if ($this->fail || str_contains(strtolower($website), 'unreachable')) {
            $this->fail = false;
            throw new WebsiteUnreachable($website);
        }
        if (null !== $this->next) {
            $snapshot = $this->next;
            $this->next = null;

            return $snapshot;
        }
        $host = strtolower((string) (parse_url($website, \PHP_URL_HOST) ?: $website));
        $palette = self::PALETTES[crc32($host) % \count(self::PALETTES)];

        return new BrandSnapshot(
            $website,
            ucfirst(explode('.', $host)[0]),
            $palette['colors'],
            $palette['fonts'],
            [],
            \sprintf('body{background:%s;color:%s;font-family:"%s"} a,.btn{color:%s}', $palette['colors'][0], $palette['colors'][1], $palette['fonts'][0], $palette['colors'][2]),
        );
    }

    /** The next extract() answers this (tests). */
    public function willReturn(BrandSnapshot $snapshot): void
    {
        $this->next = $snapshot;
    }

    /** The next extract() fails as unreachable (tests). */
    public function willFail(): void
    {
        $this->fail = true;
    }
}
