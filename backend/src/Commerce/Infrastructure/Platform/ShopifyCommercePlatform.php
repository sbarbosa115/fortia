<?php

namespace App\Commerce\Infrastructure\Platform;

use App\Commerce\Application\Port\CommercePlatform;
use App\Commerce\Application\Port\PlatformTokens;
use App\Commerce\Domain\CatalogItem;
use App\Commerce\Domain\Error\ShopifyTokenExpired;
use App\Commerce\Domain\Error\TokenExchangeFailed;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\UpstreamFailed;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Shopify (COMMERCE_PROVIDER=shopify, PRD §13.10): OAuth with the read_products scope and expiring offline tokens
 * (`expiring=1`, refreshed with the refresh token), products through the Admin REST API (first variant's price,
 * up to 8 pages of 250), webhooks signed with the app secret. The shop is always a validated *.myshopify.com host.
 */
final class ShopifyCommercePlatform implements CommercePlatform
{
    private const API_VERSION = '2025-07';
    private const MAX_PAGES = 8;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly Clock $clock,
        #[Autowire('%env(SHOPIFY_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(SHOPIFY_API_SECRET)%')]
        private readonly string $apiSecret,
    ) {
    }

    public function authorizeUrl(string $shop, string $state, string $redirectUri): string
    {
        return 'https://'.$shop.'/admin/oauth/authorize?'.http_build_query([
            'client_id' => $this->apiKey,
            'scope' => 'read_products',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $shop, string $code): PlatformTokens
    {
        try {
            return $this->tokens($shop, ['code' => $code, 'expiring' => 1]);
        } catch (ExceptionInterface|\UnexpectedValueException $e) {
            throw new TokenExchangeFailed($e);
        }
    }

    public function refresh(string $shop, string $refreshToken): PlatformTokens
    {
        try {
            return $this->tokens($shop, ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
        } catch (ExceptionInterface|\UnexpectedValueException) {
            throw new ShopifyTokenExpired();
        }
    }

    public function products(string $shop, string $accessToken): array
    {
        $url = 'https://'.$shop.'/admin/api/'.self::API_VERSION.'/products.json?limit=250&fields=id,title,body_html,handle,images,variants';
        $items = [];
        try {
            for ($page = 0; null !== $url && $page < self::MAX_PAGES; ++$page) {
                $response = $this->http->request('GET', $url, ['timeout' => 15, 'headers' => ['X-Shopify-Access-Token' => $accessToken, 'Accept' => 'application/json']]);
                $status = $response->getStatusCode();
                if (401 === $status || 403 === $status) {
                    throw new ShopifyTokenExpired();
                }
                if ($status >= 400) {
                    throw new UpstreamFailed('SHOPIFY_UNAVAILABLE', 'Shopify did not answer. Please try again.');
                }
                foreach ((array) ($response->toArray()['products'] ?? []) as $product) {
                    $item = \is_array($product) ? self::item($shop, $product) : null;
                    if (null !== $item) {
                        $items[] = $item;
                    }
                }
                $url = self::nextPage($response->getHeaders(false)['link'][0] ?? '');
            }
        } catch (ExceptionInterface $e) {
            throw new UpstreamFailed('SHOPIFY_UNAVAILABLE', 'Shopify did not answer. Please try again.', [], $e);
        }

        return $items;
    }

    public function webhookSecret(): string
    {
        return $this->apiSecret;
    }

    /**
     * @param array<string, mixed> $grant
     *
     * @throws ExceptionInterface
     */
    private function tokens(string $shop, array $grant): PlatformTokens
    {
        $data = $this->http->request('POST', 'https://'.$shop.'/admin/oauth/access_token', [
            'timeout' => 15,
            'json' => ['client_id' => $this->apiKey, 'client_secret' => $this->apiSecret] + $grant,
        ])->toArray();
        $access = $data['access_token'] ?? null;
        if (!\is_string($access) || '' === $access) {
            throw new \UnexpectedValueException('No access token in the answer.');
        }
        $expiresIn = $data['expires_in'] ?? null;

        return new PlatformTokens(
            $access,
            \is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            \is_int($expiresIn) ? $this->clock->now()->modify(\sprintf('+%d seconds', $expiresIn)) : null,
        );
    }

    /** @param array<string, mixed> $product */
    private static function item(string $shop, array $product): ?CatalogItem
    {
        $variants = \is_array($product['variants'] ?? null) ? array_values($product['variants']) : [];
        $images = \is_array($product['images'] ?? null) ? array_values($product['images']) : [];
        $handle = \is_string($product['handle'] ?? null) ? $product['handle'] : null;

        return CatalogItem::from(
            $product['title'] ?? null,
            $product['body_html'] ?? '',
            \is_array($variants[0] ?? null) ? ($variants[0]['price'] ?? null) : null,
            \is_array($images[0] ?? null) ? ($images[0]['src'] ?? null) : null,
            null === $handle ? null : 'https://'.$shop.'/products/'.rawurlencode($handle),
        );
    }

    private static function nextPage(string $link): ?string
    {
        return 1 === preg_match('/<([^>]+)>;\s*rel="next"/', $link, $match) ? $match[1] : null;
    }
}
