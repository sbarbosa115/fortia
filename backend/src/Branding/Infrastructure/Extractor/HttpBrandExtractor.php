<?php

namespace App\Branding\Infrastructure\Extractor;

use App\Branding\Application\Port\BrandExtractor;
use App\Branding\Application\Port\BrandSnapshot;
use App\Branding\Domain\Error\WebsiteUnreachable;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads a website over plain HTTP (BRAND_EXTRACTOR=http): the page, its inline CSS and up to three of its stylesheets.
 * It collects the colours and fonts the CSS uses (most used first) and the images that may be the logo (images named
 * or labelled "logo", the touch icon, the Open Graph image). The PRD's headless browser (§7.16) is left out on
 * purpose: CSS added by scripts is not seen (docs/pdr/prd-mappi.md "Left out").
 *
 * Requests never reach private networks (SSRF), stop after 10 s and read at most 2 MB of HTML and 512 KB per sheet.
 */
final class HttpBrandExtractor implements BrandExtractor
{
    private const MAX_HTML = 2_000_000;
    private const MAX_CSS = 512_000;
    private const MAX_SHEETS = 3;
    private const GENERIC_FONTS = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'inherit', 'initial', 'unset', 'ui-sans-serif', 'ui-serif', 'ui-monospace', '-apple-system', 'blinkmacsystemfont', 'emoji'];

    private readonly HttpClientInterface $http;

    public function __construct(HttpClientInterface $http)
    {
        $this->http = new NoPrivateNetworkHttpClient($http);
    }

    public function extract(string $website): BrandSnapshot
    {
        $scheme = strtolower((string) parse_url($website, \PHP_URL_SCHEME));
        if (!\in_array($scheme, ['http', 'https'], true) || '' === (string) parse_url($website, \PHP_URL_HOST)) {
            throw new WebsiteUnreachable($website);
        }
        $html = $this->fetch($website, self::MAX_HTML);
        if (null === $html) {
            throw new WebsiteUnreachable($website);
        }

        $document = \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR | \Dom\HTML_NO_DEFAULT_NS);
        $css = '';
        foreach ($document->querySelectorAll('style') as $style) {
            $css .= $style->textContent."\n";
        }
        foreach ($document->querySelectorAll('[style]') as $element) {
            $css .= '*{'.$element->getAttribute('style')."}\n";
        }
        $fonts = [];
        $sheets = 0;
        foreach ($document->querySelectorAll('link[rel~="stylesheet"][href]') as $link) {
            $href = self::absolute((string) $link->getAttribute('href'), $website);
            if (null === $href) {
                continue;
            }
            if (preg_match('/[?&]family=([^&:]+)/', $href, $match)) {
                $fonts[] = str_replace('+', ' ', urldecode($match[1]));
                continue;
            }
            if ($sheets++ < self::MAX_SHEETS) {
                $css .= ($this->fetch($href, self::MAX_CSS) ?? '')."\n";
            }
        }

        return new BrandSnapshot(
            $website,
            trim((string) $document->querySelector('title')?->textContent),
            self::colors($css),
            array_values(array_unique([...$fonts, ...self::fonts($css)])),
            self::logoCandidates($document, $website),
            mb_substr($css, 0, 20_000),
        );
    }

    private function fetch(string $url, int $limit): ?string
    {
        try {
            $response = $this->http->request('GET', $url, [
                'timeout' => 10,
                'max_duration' => 10,
                'max_redirects' => 3,
                'headers' => ['Accept' => 'text/html,text/css;q=0.9,*/*;q=0.5', 'User-Agent' => 'MappiBrandReader/1.0'],
            ]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            $body = '';
            foreach ($this->http->stream($response) as $chunk) {
                $body .= $chunk->getContent();
                if (\strlen($body) > $limit) {
                    $response->cancel();
                    break;
                }
            }

            return substr($body, 0, $limit);
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /** @return list<string> #rrggbb colours, most used first */
    private static function colors(string $css): array
    {
        $counts = [];
        preg_match_all('/#([0-9a-f]{6}|[0-9a-f]{3})\b/i', $css, $hex);
        foreach ($hex[1] as $digits) {
            $digits = strtolower(3 === \strlen($digits) ? implode('', array_map(static fn (string $d): string => $d.$d, str_split($digits))) : $digits);
            $counts['#'.$digits] = ($counts['#'.$digits] ?? 0) + 1;
        }
        preg_match_all('/rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})/i', $css, $rgb, \PREG_SET_ORDER);
        foreach ($rgb as $match) {
            $color = \sprintf('#%02x%02x%02x', min(255, (int) $match[1]), min(255, (int) $match[2]), min(255, (int) $match[3]));
            $counts[$color] = ($counts[$color] ?? 0) + 1;
        }
        arsort($counts);

        return \array_slice(array_keys($counts), 0, 24);
    }

    /** @return list<string> */
    private static function fonts(string $css): array
    {
        $counts = [];
        preg_match_all('/font-family\s*:\s*([^;}{]+)/i', $css, $matches);
        foreach ($matches[1] as $list) {
            $first = trim(explode(',', $list)[0], " \t\n\r\"'");
            if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9 _-]{0,59}$/', $first) || \in_array(strtolower($first), self::GENERIC_FONTS, true)) {
                continue;
            }
            $counts[$first] = ($counts[$first] ?? 0) + 1;
        }
        arsort($counts);

        return \array_slice(array_map('strval', array_keys($counts)), 0, 8);
    }

    /** @return list<string> */
    private static function logoCandidates(\Dom\HTMLDocument $document, string $base): array
    {
        $found = [];
        foreach ($document->querySelectorAll('img') as $image) {
            $hints = strtolower(implode(' ', [$image->getAttribute('src'), $image->getAttribute('alt'), $image->getAttribute('class'), $image->getAttribute('id')]));
            if (str_contains($hints, 'logo')) {
                $found[] = (string) $image->getAttribute('src');
            }
        }
        foreach ($document->querySelectorAll('link[rel~="apple-touch-icon"][href], link[rel~="icon"][href]') as $icon) {
            $found[] = (string) $icon->getAttribute('href');
        }
        $og = $document->querySelector('meta[property="og:image"][content]');
        if (null !== $og) {
            $found[] = (string) $og->getAttribute('content');
        }
        $urls = array_filter(array_map(static fn (string $u): ?string => self::absolute($u, $base), $found));

        return \array_slice(array_values(array_unique($urls)), 0, 10);
    }

    private static function absolute(string $url, string $base): ?string
    {
        $url = trim($url);
        if ('' === $url || str_starts_with($url, 'data:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $scheme = (string) parse_url($base, \PHP_URL_SCHEME);
        $host = (string) parse_url($base, \PHP_URL_HOST);
        $port = parse_url($base, \PHP_URL_PORT);
        $origin = $scheme.'://'.$host.(null === $port ? '' : ':'.$port);
        if (str_starts_with($url, '//')) {
            return $scheme.':'.$url;
        }
        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }
        $path = (string) parse_url($base, \PHP_URL_PATH);
        $dir = str_ends_with($path, '/') ? $path : \dirname('' === $path ? '/' : $path).'/';

        return $origin.str_replace('//', '/', $dir.$url);
    }
}
