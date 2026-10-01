<?php

namespace App\Tests\Functional\Api\Commerce;

use App\Commerce\Domain\Model\Product;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** PRD §8.6 products: the public catalog and the console's CRUD of its own catalog (§10.19), D11 and D16/D17. */
final class ProductsApiTest extends ApiTestCase
{
    public function testAWriterCreatesEditsAndDeletesItsProducts(): void
    {
        $owner = $this->account('ACME0001');

        $created = $this->data($this->api('POST', '/api/v1/customer/ACME0001/products', [
            'name' => 'Teapot',
            'description' => '<p onclick="x()">Cast iron <script>alert(1)</script><strong>1 L</strong></p>',
            'price' => 30,
            'image_url' => 'https://cdn.example.com/teapot.jpg',
            'product_url' => 'https://tea.example.com/products/teapot',
        ], as: $owner), 201);

        self::assertSame('Teapot', $created['name']);
        self::assertSame(30.0, (float) $created['price']);
        self::assertStringNotContainsString('<script', $created['description'], 'D11: product HTML is sanitized when stored');
        self::assertStringNotContainsString('onclick', $created['description']);
        self::assertStringContainsString('<strong>1 L</strong>', $created['description'], 'safe formatting stays');
        $id = $created['product_id'];

        $updated = $this->data($this->api('PUT', '/api/v1/customer/ACME0001/products/'.$id, ['name' => 'Teapot XL', 'price' => null], as: $owner));
        self::assertSame('Teapot XL', $updated['name']);
        self::assertNull($updated['price'], 'PUT replaces the whole product');
        self::assertSame('Teapot XL', $this->data($this->api('GET', '/api/v1/customer/ACME0001/products/'.$id, as: $owner))['name']);

        self::assertSame(204, $this->api('DELETE', '/api/v1/customer/ACME0001/products/'.$id, as: $owner)['status']);
        $this->assertApiError($this->api('GET', '/api/v1/customer/ACME0001/products/'.$id, as: $owner), 404, 'PRODUCT_NOT_FOUND');
    }

    public function testAnotherTenantsCatalogIsNotFound(): void
    {
        $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $id = $this->product('ACME0001', 'Acme mug');

        $this->assertApiError($this->api('GET', '/api/v1/customer/ACME0001/products/'.$id, as: $globex), 404, 'CUSTOMER_NOT_FOUND', 'another tenant\'s id is 404, never 403');
        $this->assertApiError($this->api('GET', '/api/v1/customer/GLOBEX01/products/'.$id, as: $globex), 404, 'PRODUCT_NOT_FOUND', 'another tenant\'s product under my account is 404');
        $this->assertApiError($this->api('PUT', '/api/v1/customer/GLOBEX01/products/'.$id, ['name' => 'Mine'], as: $globex), 404, 'PRODUCT_NOT_FOUND');
        $this->assertApiError($this->api('DELETE', '/api/v1/customer/GLOBEX01/products/'.$id, as: $globex), 404, 'PRODUCT_NOT_FOUND');
        $this->assertApiError($this->api('POST', '/api/v1/customer/ACME0001/products', ['name' => 'x'], as: $globex), 404, 'CUSTOMER_NOT_FOUND');
        self::assertSame(0, $this->data($this->api('GET', '/api/v1/products', as: $globex))['total'], 'the listing shows only the caller\'s catalog');
        self::assertNotNull($this->em()->find(Product::class, $id));
    }

    public function testReadOnlyUsersCannotWriteAndValidationApplies(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        $id = $this->product('ACME0001', 'Acme mug');

        $this->assertApiError($this->api('POST', '/api/v1/customer/ACME0001/products', ['name' => 'x'], as: 'reader@acme.test'), 403, 'FORBIDDEN');
        $this->assertApiError($this->api('DELETE', '/api/v1/customer/ACME0001/products/'.$id, as: 'reader@acme.test'), 403, 'FORBIDDEN');
        self::assertSame(200, $this->api('GET', '/api/v1/customer/ACME0001/products/'.$id, as: 'reader@acme.test')['status'], 'reading is allowed');
        $this->assertApiError($this->api('POST', '/api/v1/customer/ACME0001/products', ['name' => ' '], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('POST', '/api/v1/customer/ACME0001/products', ['name' => 'x', 'price' => -1], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('POST', '/api/v1/customer/ACME0001/products', ['name' => 'x', 'product_url' => 'javascript:alert(1)'], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('GET', '/api/v1/customer/ACME0001/products/nope', as: $owner), 400, 'INVALID_UUID');
        $this->assertApiError($this->api('GET', '/api/v1/products'), 401, 'UNAUTHORIZED');
    }

    public function testThePublicCatalogIsBare(): void
    {
        $this->account('ACME0001');
        $this->product('ACME0001', 'Acme mug');

        $response = $this->api('GET', '/api/v1/customer/ACME0001/products');

        self::assertSame(200, $response['status']);
        self::assertSame(['product_id', 'name', 'description', 'price', 'image_url', 'product_url'], array_keys($response['json'][0]), '§8.6: bare [{product_id, name, description, price, image_url, product_url}]');
    }

    public function testTheListingIsPaginatedAndSearchedInTheDatabase(): void
    {
        $owner = $this->account('ACME0001');
        foreach (['Café Colombia', 'Café Etiopía', 'Té verde', 'Taza 100% cerámica'] as $name) {
            $this->product('ACME0001', $name);
        }

        $page = $this->data($this->api('GET', '/api/v1/products?page=2&page_size=3', as: $owner));
        self::assertSame([4, 2, 3, 2], [$page['total'], $page['page'], $page['page_size'], $page['total_pages']]);
        self::assertCount(1, $page['items']);

        $found = $this->data($this->api('GET', '/api/v1/products?search='.rawurlencode('cafe colombia'), as: $owner));
        self::assertSame(['Café Colombia'], array_column($found['items'], 'name'), 'every word must match, ignoring accents');
        self::assertSame(['Taza 100% cerámica'], array_column($this->data($this->api('GET', '/api/v1/products?search=100%25', as: $owner))['items'], 'name'), '% matches literally');
    }

    private function product(string $customerId, string $name): string
    {
        $product = new Product(Ids::uuid4(), $customerId, $name, new \DateTimeImmutable());
        $product->describe($name, '<p>Nice</p>', '10.00', null, null, new \DateTimeImmutable());
        $this->em()->persist($product);
        $this->em()->flush();

        return $product->productId();
    }
}
