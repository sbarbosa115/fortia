<?php

namespace App\Commerce\Infrastructure\Html;

use App\Commerce\Application\Port\ProductHtml;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Product descriptions through Symfony's HtmlSanitizer (D11): the safe elements and attributes of the W3C sanitizer
 * API, links and images over https/http only, no scripts, styles, iframes or event handlers. The respondent app
 * sanitizes again with DOMPurify before rendering.
 */
final class SymfonyProductHtml implements ProductHtml
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new HtmlSanitizer((new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http'])
            ->withMaxInputLength(100_000));
    }

    public function sanitize(string $html): string
    {
        return trim($this->sanitizer->sanitize($html));
    }
}
