<?php

namespace App\Commerce\UI\Http\Controller;

use App\Commerce\Application\Command\DeleteProduct;
use App\Commerce\Application\Command\SaveProduct;
use App\Commerce\Application\Query\ProductQueries;
use App\Commerce\Domain\Error\ProductNotFound;
use App\Commerce\UI\Http\Input\ProductInput;
use App\Commerce\UI\Http\Output\CatalogPageOutput;
use App\Commerce\UI\Http\Output\CatalogProductOutput;
use App\Identity\Application\Query\AccountQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\UI\Http\Output\Document\ProductOutput;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The product catalog (PRD §8.6): the public catalog of an account (bare, what a store widget or respondent reads),
 * the console's CRUD of its own catalog (the hidden /products screen, §10.19) and its paginated listing. Another
 * account's catalog is 404, never 403; writes need write permission (Customer-Read-Only gets 403).
 */
#[OA\Tag(name: 'Products')]
final class ProductsController
{
    public function __construct(
        private readonly ProductQueries $products,
        private readonly AccountQueries $accounts,
        private readonly CommandBus $commands,
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
    ) {
    }

    /** P (D4: kept public, rate limited). Bare: [{product_id, name, description, price, image_url, product_url}]. */
    #[Route('/customer/{customer_id}/products', name: 'api_products_public', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The account\'s catalog (bare)', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ProductOutput::class))))]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function catalog(string $customer_id, Request $request): JsonResponse
    {
        if (!$this->publicApiLimiter->create('products|'.$request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many requests. Please try again in a minute.');
        }

        return ApiResponse::bare(array_map(ProductOutput::fromArray(...), $this->products->catalogOf($customer_id)));
    }

    /** A. The console's listing of the caller's catalog: newest first, `search` matches every word of the name. */
    #[Route('/products', name: 'api_products_list', methods: ['GET'])]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'page_size', in: 'query', schema: new OA\Schema(type: 'integer', maximum: 100))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 200))]
    #[OA\Response(response: 200, description: 'One page of the catalog', content: new Model(type: CatalogPageOutput::class))]
    public function list(Caller $caller, Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = max(1, min(100, (int) $request->query->get('page_size', '20')));
        $found = $this->products->page($caller->customerId, (string) $request->query->get('search', ''), $page, $pageSize);

        return ApiResponse::ok(CatalogPageOutput::of($found['items'], $page, $pageSize, $found['total']));
    }

    #[Route('/customer/{customer_id}/products', name: 'api_products_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: ProductInput::class))]
    #[OA\Response(response: 201, description: 'The new product', content: new Model(type: CatalogProductOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only)')]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    public function create(Caller $caller, string $customer_id, #[Payload(allowExtraFields: false)] ProductInput $input): JsonResponse
    {
        self::requireWrite($caller);
        $customerId = $this->ownAccount($caller, $customer_id);
        $id = (string) $this->commands->dispatch(new SaveProduct($customerId, $input->item()));

        return ApiResponse::created($this->output($customerId, $id));
    }

    #[Route('/customer/{customer_id}/products/{product_id}', name: 'api_products_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The product', content: new Model(type: CatalogProductOutput::class))]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND, PRODUCT_NOT_FOUND')]
    public function get(Caller $caller, string $customer_id, string $product_id): JsonResponse
    {
        $customerId = $this->ownAccount($caller, $customer_id);

        return ApiResponse::ok($this->output($customerId, RouteId::uuid($product_id)));
    }

    #[Route('/customer/{customer_id}/products/{product_id}', name: 'api_products_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: ProductInput::class))]
    #[OA\Response(response: 200, description: 'The saved product', content: new Model(type: CatalogProductOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only)')]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND, PRODUCT_NOT_FOUND')]
    public function update(Caller $caller, string $customer_id, string $product_id, #[Payload(allowExtraFields: false)] ProductInput $input): JsonResponse
    {
        self::requireWrite($caller);
        $customerId = $this->ownAccount($caller, $customer_id);
        $id = (string) $this->commands->dispatch(new SaveProduct($customerId, $input->item(), RouteId::uuid($product_id)));

        return ApiResponse::ok($this->output($customerId, $id), 'Saved');
    }

    #[Route('/customer/{customer_id}/products/{product_id}', name: 'api_products_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only)')]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND, PRODUCT_NOT_FOUND')]
    public function delete(Caller $caller, string $customer_id, string $product_id): Response
    {
        self::requireWrite($caller);
        $customerId = $this->ownAccount($caller, $customer_id);
        $this->commands->dispatch(new DeleteProduct($customerId, RouteId::uuid($product_id)));

        return ApiResponse::noContent();
    }

    private function output(string $customerId, string $productId): CatalogProductOutput
    {
        return CatalogProductOutput::fromArray($this->products->find($customerId, $productId) ?? throw new ProductNotFound());
    }

    /** The account in the path: the caller's own (or any, for an Admin); another tenant's is 404. */
    private function ownAccount(Caller $caller, string $customerId): string
    {
        if (!$caller->owns($customerId) || !$this->accounts->exists($customerId)) {
            throw new NotFound('CUSTOMER_NOT_FOUND', 'Customer not found.');
        }

        return $customerId;
    }

    private static function requireWrite(Caller $caller): void
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', 'You do not have permission to change the catalog.');
        }
    }
}
