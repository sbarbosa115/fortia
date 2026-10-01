<?php

namespace App\Branding\Infrastructure\Extractor;

use App\Branding\Application\Port\BrandExtractor;

/** BRAND_EXTRACTOR picks the adapter: "http" reads real websites; anything else (the default "fake") runs offline. */
final class BrandExtractorFactory
{
    public function __construct(
        private readonly HttpBrandExtractor $http,
        private readonly FakeBrandExtractor $fake,
    ) {
    }

    public function create(string $provider): BrandExtractor
    {
        return 'http' === $provider ? $this->http : $this->fake;
    }
}
