<?php

namespace App\Tests\Unit\Commerce;

use App\Commerce\Domain\CatalogItem;
use App\Commerce\Domain\ShopDomain;
use App\Commerce\Domain\StoreOrigin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** PRD §7.17 step 1 and §10.5 "Via website": the store URL reduced to its origin; §8.6 the shop pattern. */
final class StoreUrlsTest extends TestCase
{
    /** @return iterable<string, array{string, string|null}> */
    public static function urls(): iterable
    {
        yield 'path, query and fragment dropped' => ['https://Shop.Example.com/collections/all?page=2#top', 'https://shop.example.com'];
        yield 'scheme added when missing' => ['shop.example.com/products', 'https://shop.example.com'];
        yield 'http kept' => ['http://shop.example.com', 'http://shop.example.com'];
        yield 'port kept' => ['https://shop.example.com:8443/x', 'https://shop.example.com:8443'];
        yield 'surrounding spaces trimmed' => ['  shop.example.com  ', 'https://shop.example.com'];
        yield 'a host without a dot is refused' => ['localhost', null];
        yield 'empty' => ['', null];
        yield 'spaces inside' => ['shop example.com', null];
        yield 'another scheme' => ['ftp://shop.example.com', null];
        yield 'javascript' => ['javascript:alert(1)', null];
    }

    #[DataProvider('urls')]
    public function testTheStoreUrlIsReducedToItsOrigin(string $url, ?string $origin): void
    {
        self::assertSame($origin, StoreOrigin::of($url), '§10.5: the scheme is added if missing; the host must contain a dot');
    }

    public function testOnlyMyshopifyStoresAreShops(): void
    {
        self::assertTrue(ShopDomain::isValid('acme-store.myshopify.com'));
        self::assertTrue(ShopDomain::isValid('a1.myshopify.com'));
        self::assertFalse(ShopDomain::isValid('-acme.myshopify.com'), '§8.6: [a-z0-9][a-z0-9-]*');
        self::assertFalse(ShopDomain::isValid('Acme.myshopify.com'), 'lowercase only');
        self::assertFalse(ShopDomain::isValid('acme.myshopify.com.evil.test'), 'the whole value must match');
        self::assertFalse(ShopDomain::isValid('evil.test/acme.myshopify.com'));
        self::assertFalse(ShopDomain::isValid(null));
        self::assertSame('https://acme.myshopify.com', ShopDomain::origin('acme.myshopify.com'));
    }

    public function testACatalogItemIsBuiltLeniently(): void
    {
        $item = CatalogItem::from('  Trail <b>shoe</b>  ', '<p>Grip</p>', '$1,299.90', '//cdn.example.com/a.jpg', 'javascript:alert(1)');

        self::assertNotNull($item);
        self::assertSame('Trail shoe', $item->name, 'tags and extra spaces are removed from the name');
        self::assertSame('1299.90', $item->price, '§6.11: the price is parsed leniently from text');
        self::assertSame('https://cdn.example.com/a.jpg', $item->imageUrl, 'a protocol-relative image URL gets https');
        self::assertNull($item->productUrl, 'only http(s) URLs are kept');
        self::assertNull(CatalogItem::from('   ', '', null, null, null), 'a product without a name is dropped');
        self::assertSame(['name' => 'Trail shoe', 'description' => '<p>Grip</p>', 'price' => 1299.9, 'image_url' => 'https://cdn.example.com/a.jpg', 'product_url' => null], $item->toArray());
    }
}
