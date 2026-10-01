<?php

namespace App\Branding\Application\Port;

use App\Branding\Domain\Error\WebsiteUnreachable;

/**
 * Reads a website's look (PRD §7.16 step 1): its colours, fonts, CSS and logo candidates. The real adapter fetches the
 * page over HTTP; dev and tests use a deterministic fake (BRAND_EXTRACTOR=fake).
 */
interface BrandExtractor
{
    /** @throws WebsiteUnreachable */
    public function extract(string $website): BrandSnapshot;
}
