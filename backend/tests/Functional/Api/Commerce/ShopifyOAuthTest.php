<?php

namespace App\Tests\Functional\Api\Commerce;

use App\Commerce\Domain\Model\Product;
use App\Commerce\Domain\Model\ShopifyConnection;
use App\Commerce\Domain\OAuthState;
use App\Commerce\Infrastructure\Platform\FakeCommercePlatform;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/**
 * PRD §8.6 / §13.10 e-commerce platform: OAuth (read-only products scope) with a signed, expiring state (D5), the
 * callback that stores the tokens and syncs on the first connection, the connection and the product sync (§7.17).
 */
final class ShopifyOAuthTest extends ApiTestCase
{
    private const SHOP = 'acme-store.myshopify.com';

    public function testAuthorizingGivesTheUrlWithASignedState(): void
    {
        $owner = $this->account('ACME0001');

        $url = $this->data($this->api('GET', '/api/v1/auth/shopify?shop='.self::SHOP, as: $owner))['url'];

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertStringContainsString(FakeCommercePlatform::AUTHORIZE_PATH, $url, 'dev runs offline on the fake platform\'s page');
        self::assertSame('read_products', $query['scope'], '§8.6: a read-only products scope');
        self::assertSame(self::SHOP, $query['shop']);
        self::assertStringEndsWith('/api/v1/auth/shopify/callback', (string) $query['redirect_uri']);
        self::assertNotSame('ACME0001', $query['state'], 'D5: the state is no longer the bare customer_id');
        self::assertSame('ACME0001', OAuthState::verify((string) $query['state'], self::SHOP, $this->clock()->now(), $this->secret()), 'D5: a signed nonce for this account and shop');
    }

    public function testOnlyMyshopifyStoresAndWritersMayAuthorize(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('GET', '/api/v1/auth/shopify?shop=evil.test', as: $owner), 400, 'INVALID_REQUEST', '§8.6: shop matching [a-z0-9][a-z0-9-]*.myshopify.com');
        $this->assertApiError($this->api('GET', '/api/v1/auth/shopify', as: $owner), 400, 'INVALID_REQUEST');
        $this->assertApiError($this->api('GET', '/api/v1/auth/shopify?shop='.self::SHOP, as: 'reader@acme.test'), 403, 'FORBIDDEN', 'read-only users cannot connect a store');
        $this->assertApiError($this->api('GET', '/api/v1/auth/shopify?shop='.self::SHOP), 401, 'UNAUTHORIZED');
    }

    public function testTheCallbackStoresTheTokensAndSyncsOnTheFirstConnection(): void
    {
        $this->account('ACME0001');

        $response = $this->oauthCallback(['code' => 'fake-abc', 'shop' => self::SHOP, 'state' => $this->state('ACME0001')]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'), '§8.6: an HTML page…');
        self::assertStringContainsString('window.close()', (string) $response->getContent(), '…that closes itself');
        $connection = $this->connection('ACME0001');
        self::assertSame(self::SHOP, $connection->shop());
        self::assertStringStartsWith('fake-access-', $connection->accessToken(), 'the token is stored');
        self::assertStringStartsWith('fake-refresh-', (string) $connection->refreshToken(), '…with its refresh token');
        $products = $this->em()->getRepository(Product::class)->findBy(['customerId' => 'ACME0001']);
        self::assertCount(FakeCommercePlatform::CATALOG_SIZE, $products, '§8.6: syncs products on the first connection');
        self::assertSame('https://'.self::SHOP, $products[0]->sourceUrl());
    }

    public function testAReconnectionKeepsTheCatalogAsItIs(): void
    {
        $this->account('ACME0001');
        $this->connect('ACME0001');
        $mine = new Product(Ids::uuid4(), 'ACME0001', 'Hand-made', new \DateTimeImmutable());
        $this->em()->persist($mine);
        $this->em()->flush();

        $response = $this->oauthCallback(['code' => 'fake-again', 'shop' => self::SHOP, 'state' => $this->state('ACME0001')]);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($this->em()->find(Product::class, $mine->productId()), 'only the first connection syncs');
    }

    public function testAForgedExpiredOrForeignStateIsRefused(): void
    {
        $this->account('ACME0001');
        $this->account('GLOBEX01');
        $valid = $this->state('ACME0001');
        [$payload, $signature] = explode('.', $valid);
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        $data['c'] = 'GLOBEX01';
        $forged = rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=').'.'.$signature;

        $this->assertCallbackError(['code' => 'fake-a', 'shop' => self::SHOP, 'state' => 'GLOBEX01'], 400, 'INVALID_REQUEST', 'D5: the legacy state (a bare customer_id) is refused');
        $this->assertCallbackError(['code' => 'fake-a', 'shop' => self::SHOP, 'state' => $forged], 400, 'INVALID_REQUEST', 'D5: a state whose customer was changed is refused');
        $this->assertCallbackError(['code' => 'fake-a', 'shop' => 'other.myshopify.com', 'state' => $valid], 400, 'INVALID_REQUEST', 'a state is bound to its shop');
        $this->assertCallbackError(['shop' => self::SHOP, 'state' => $valid], 400, 'INVALID_REQUEST', '§8.6: code, shop and state are required');
        $this->clock()->set($this->clock()->now()->modify('+16 minutes')->format(\DATE_ATOM));
        $this->assertCallbackError(['code' => 'fake-a', 'shop' => self::SHOP, 'state' => $valid], 400, 'INVALID_REQUEST', 'D5: the nonce expires');

        self::assertNull($this->em()->find(ShopifyConnection::class, 'GLOBEX01'), 'nobody can bind a store to another account');
        self::assertNull($this->em()->find(ShopifyConnection::class, 'ACME0001'));
    }

    public function testACallbackForAnUnknownAccountOrABadCodeFails(): void
    {
        $this->account('ACME0001');

        $this->assertCallbackError(['code' => 'fake-a', 'shop' => self::SHOP, 'state' => $this->state('NOPE0001')], 404, 'CUSTOMER_NOT_FOUND', '§8.6');
        $this->assertCallbackError(['code' => 'fake-refused', 'shop' => self::SHOP, 'state' => $this->state('ACME0001')], 400, 'TOKEN_EXCHANGE_FAILED', '§8.6');
        self::assertNull($this->em()->find(ShopifyConnection::class, 'ACME0001'));
    }

    public function testTheConnectionShowsTheShopOrNull(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');

        self::assertSame(['shop' => null], $this->data($this->api('GET', '/api/v1/shopify/connection', as: $owner)), '§8.6: {shop | null}');
        $this->connect('GLOBEX01');
        self::assertSame(['shop' => null], $this->data($this->api('GET', '/api/v1/shopify/connection', as: $owner)), 'another account\'s store is never shown');
        $this->connect('ACME0001');
        self::assertSame(['shop' => self::SHOP], $this->data($this->api('GET', '/api/v1/shopify/connection', as: $owner)));
        self::assertStringNotContainsString('fake-access', $this->api('GET', '/api/v1/shopify/connection', as: $owner)['body'], 'tokens are secrets');
    }

    public function testSyncingRefreshesTheTokenAndReplacesAllProducts(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        $this->connect('ACME0001', refreshToken: 'fake-refresh-old');
        $scraped = new Product(Ids::uuid4(), 'ACME0001', 'Scraped mug', new \DateTimeImmutable());
        $scraped->placeIn('https://mugs.example.com', null);
        $other = new Product(Ids::uuid4(), 'GLOBEX01', 'Globex item', new \DateTimeImmutable());
        $this->em()->persist($scraped);
        $this->em()->persist($other);
        $this->em()->flush();

        $synced = $this->data($this->api('GET', '/api/v1/shopify/sync/products', as: $owner));

        self::assertSame(self::SHOP, $synced['shop']);
        self::assertCount(FakeCommercePlatform::CATALOG_SIZE, $synced['products']);
        self::assertNotContains('Scraped mug', array_column($synced['products'], 'name'), '§7.17: the sync replaces all of the account\'s products');
        self::assertNotNull($this->em()->find(Product::class, $other->productId()), 'another tenant\'s products are never touched');
        self::assertNotSame('fake-refresh-old', $this->connection('ACME0001')->refreshToken(), '§7.17: the token is refreshed when there is a refresh token');
    }

    public function testSyncingNeedsAConnectedStoreWithAValidToken(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('GET', '/api/v1/shopify/sync/products', as: $owner), 400, 'SHOPIFY_NOT_CONNECTED', '§8.6');

        $this->connect('ACME0001', refreshToken: 'revoked');
        $this->assertApiError($this->api('GET', '/api/v1/shopify/sync/products', as: $owner), 400, 'SHOPIFY_TOKEN_EXPIRED', '§8.6: the refresh was refused');

        $this->connect('ACME0001', refreshToken: null, expiresAt: $this->clock()->now()->modify('-1 minute'));
        $this->assertApiError($this->api('GET', '/api/v1/shopify/sync/products', as: $owner), 400, 'SHOPIFY_TOKEN_EXPIRED', 'an expired token without a refresh token');

        $this->assertApiError($this->api('GET', '/api/v1/shopify/sync/products', as: 'reader@acme.test'), 403, 'FORBIDDEN');
    }

    public function testTheFakePlatformsPageOnlyEverReturnsToOurCallback(): void
    {
        $owner = $this->account('ACME0001');
        $url = $this->data($this->api('GET', '/api/v1/auth/shopify?shop='.self::SHOP, as: $owner))['url'];

        $this->client->request('GET', (string) parse_url($url, \PHP_URL_PATH).'?'.parse_url($url, \PHP_URL_QUERY));
        $page = (string) $this->client->getResponse()->getContent();
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('Install app', $page);
        preg_match('/href="([^"]*code=fake-[^"]*)"/', $page, $install);
        self::assertNotEmpty($install, 'Install goes back to the callback with a code');

        $this->client->request('GET', (string) parse_url(html_entity_decode($install[1]), \PHP_URL_PATH).'?'.parse_url(html_entity_decode($install[1]), \PHP_URL_QUERY));
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
        self::assertSame(self::SHOP, $this->connection('ACME0001')->shop(), 'the whole OAuth round trip works offline');

        $this->client->request('GET', FakeCommercePlatform::AUTHORIZE_PATH.'?'.http_build_query(['shop' => self::SHOP, 'state' => 'x', 'redirect_uri' => 'https://evil.test/steal']));
        self::assertSame(404, $this->client->getResponse()->getStatusCode(), 'never an open redirect');
    }

    private function state(string $customerId): string
    {
        return OAuthState::issue($customerId, self::SHOP, $this->clock()->now(), $this->secret());
    }

    private function secret(): string
    {
        return (string) static::getContainer()->getParameter('kernel.secret');
    }

    /** @param array<string, string> $query */
    private function oauthCallback(array $query): \Symfony\Component\HttpFoundation\Response
    {
        $this->client->request('GET', '/api/v1/auth/shopify/callback?'.http_build_query($query));

        return $this->client->getResponse();
    }

    /** @param array<string, string> $query */
    private function assertCallbackError(array $query, int $status, string $code, string $why): void
    {
        $response = $this->oauthCallback($query);
        self::assertSame($status, $response->getStatusCode(), $why.' — '.$response->getContent());
        self::assertSame($code, json_decode((string) $response->getContent(), true)['error']['code'] ?? null, $why);
    }

    private function connect(string $customerId, ?string $refreshToken = 'fake-refresh-x', ?\DateTimeImmutable $expiresAt = null): void
    {
        $existing = $this->em()->find(ShopifyConnection::class, $customerId);
        if (null !== $existing) {
            $existing->refresh('fake-access-x', $refreshToken, $expiresAt, new \DateTimeImmutable());
        } else {
            $this->em()->persist(new ShopifyConnection($customerId, self::SHOP, 'fake-access-x', $refreshToken, $expiresAt, new \DateTimeImmutable()));
        }
        $this->em()->flush();
    }

    private function connection(string $customerId): ShopifyConnection
    {
        $this->em()->clear();
        $connection = $this->em()->find(ShopifyConnection::class, $customerId);
        self::assertNotNull($connection, 'the connection was stored');

        return $connection;
    }
}
