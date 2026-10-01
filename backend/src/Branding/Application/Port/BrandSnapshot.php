<?php

namespace App\Branding\Application\Port;

/** What a website looks like, as the extractor found it. Everything in it is untrusted page content. */
final class BrandSnapshot
{
    /**
     * @param list<string> $colors         hex colours, most used first
     * @param list<string> $fonts          font family names, most used first
     * @param list<string> $logoCandidates absolute image URLs that may be the brand's logo
     * @param string       $css            an excerpt of the page's CSS
     */
    public function __construct(
        public readonly string $url,
        public readonly string $title,
        public readonly array $colors,
        public readonly array $fonts,
        public readonly array $logoCandidates,
        public readonly string $css,
    ) {
    }

    /** @return array{url: string, title: string, colors: list<string>, fonts: list<string>, logo_candidates: list<string>, css: string} */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'colors' => $this->colors,
            'fonts' => $this->fonts,
            'logo_candidates' => $this->logoCandidates,
            'css' => $this->css,
        ];
    }
}
