<?php

namespace App\Commerce\UI\Http\Controller;

use App\Commerce\Application\Command\ConnectShop;
use App\Commerce\Application\Port\CommercePlatform;
use App\Commerce\Application\Query\ProductQueries;
use App\Commerce\Application\Query\ShopifyQueries;
use App\Commerce\Application\ShopifySync;
use App\Commerce\Domain\Error\ShopifyNotConnected;
use App\Commerce\Domain\OAuthState;
use App\Commerce\Domain\ShopDomain;
use App\Commerce\UI\Http\Output\CatalogProductOutput;
use App\Commerce\UI\Http\Output\ShopifyAuthorizeOutput;
use App\Commerce\UI\Http\Output\ShopifyConnectionOutput;
use App\Commerce\UI\Http\Output\ShopifySyncOutput;
use App\Identity\Application\Query\AccountQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Error\Rejected;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * The connection with the e-commerce platform (PRD §8.6, §13.10): OAuth with a read-only products scope, the
 * callback that stores the tokens (and syncs the products on the first connection), the connected store and the
 * product sync. D5: the OAuth state is a signed, expiring nonce bound to the account and the shop (OAuthState).
 */
#[OA\Tag(name: 'Shopify')]
final class ShopifyController
{
    public const CALLBACK_PATH = '/api/v1/auth/shopify/callback';

    public function __construct(
        private readonly CommercePlatform $platform,
        private readonly ShopifyQueries $shopify,
        private readonly ShopifySync $sync,
        private readonly ProductQueries $products,
        private readonly AccountQueries $accounts,
        private readonly CommandBus $commands,
        private readonly Clock $clock,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $appUrl,
        #[Autowire('%kernel.secret%')]
        private readonly string $stateSecret,
    ) {
    }

    /** A, write permission. `shop` must be a *.myshopify.com store. */
    #[Route('/auth/shopify', name: 'api_shopify_authorize', methods: ['GET'])]
    #[OA\Parameter(name: 'shop', in: 'query', required: true, schema: new OA\Schema(type: 'string', pattern: '^[a-z0-9][a-z0-9-]*\.myshopify\.com$'))]
    #[OA\Response(response: 200, description: 'The authorization URL', content: new Model(type: ShopifyAuthorizeOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_REQUEST (shop missing or not a myshopify.com store)')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only)')]
    public function authorize(Caller $caller, Request $request): JsonResponse
    {
        $shop = strtolower(trim((string) $request->query->get('shop', '')));
        if (!ShopDomain::isValid($shop)) {
            throw new Rejected('INVALID_REQUEST', 'shop must be a store like your-store.myshopify.com.');
        }
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', 'You do not have permission to connect a store.');
        }
        $state = OAuthState::issue($caller->customerId, $shop, $this->clock->now(), $this->stateSecret);

        return ApiResponse::ok(new ShopifyAuthorizeOutput($this->platform->authorizeUrl($shop, $state, $this->callbackUrl())));
    }

    /**
     * P. Where the platform sends the merchant back. Verifies the state (D5), exchanges the code for the tokens and
     * stores them; the first connection also syncs the products. Answers an HTML page that closes itself.
     */
    #[Route('/auth/shopify/callback', name: 'api_shopify_callback', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'An HTML page that closes itself', content: new OA\MediaType(mediaType: 'text/html'))]
    #[OA\Response(response: 400, description: 'INVALID_REQUEST, TOKEN_EXCHANGE_FAILED')]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    public function callback(Request $request): Response
    {
        $code = trim((string) $request->query->get('code', ''));
        $shop = strtolower(trim((string) $request->query->get('shop', '')));
        $state = (string) $request->query->get('state', '');
        if ('' === $code || !ShopDomain::isValid($shop) || '' === $state) {
            throw new Rejected('INVALID_REQUEST', 'code, shop and state are required.');
        }
        $customerId = OAuthState::verify($state, $shop, $this->clock->now(), $this->stateSecret);
        if (!$this->accounts->exists($customerId)) {
            throw new NotFound('CUSTOMER_NOT_FOUND', 'Customer not found.');
        }

        $tokens = $this->platform->exchangeCode($shop, $code);
        $first = (bool) $this->commands->dispatch(new ConnectShop($customerId, $shop, $tokens));
        if ($first) {
            try {
                $this->sync->sync($customerId);
            } catch (DomainError $e) {
                // The store stays connected; the merchant can sync again from the console.
                $this->logger->warning('First product sync of {shop} failed: {message}', ['shop' => $shop, 'message' => $e->getMessage()]);
            }
        }

        return new Response($this->twig->render('commerce/shopify_connected.html.twig', ['shop' => $shop, 'cancelled' => false]), Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    #[Route('/shopify/connection', name: 'api_shopify_connection', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The connected store, or null', content: new Model(type: ShopifyConnectionOutput::class))]
    public function connection(Caller $caller): JsonResponse
    {
        return ApiResponse::ok(new ShopifyConnectionOutput($this->shopify->connectedShop($caller->customerId)));
    }

    /** A, write permission. Replaces all of the account's products with the store's (§7.17). */
    #[Route('/shopify/sync/products', name: 'api_shopify_sync', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The catalog after the sync', content: new Model(type: ShopifySyncOutput::class))]
    #[OA\Response(response: 400, description: 'SHOPIFY_NOT_CONNECTED, SHOPIFY_TOKEN_EXPIRED')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only)')]
    public function sync(Caller $caller): JsonResponse
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', 'You do not have permission to change the catalog.');
        }
        $shop = $this->shopify->connectedShop($caller->customerId) ?? throw new ShopifyNotConnected();
        $this->sync->sync($caller->customerId);

        return ApiResponse::ok(new ShopifySyncOutput($shop, array_map(CatalogProductOutput::fromArray(...), $this->products->catalogOf($caller->customerId))), 'Products synced');
    }

    private function callbackUrl(): string
    {
        return rtrim($this->appUrl, '/').self::CALLBACK_PATH;
    }

    /** @internal for the fake platform's page: whether a redirect target is our own callback */
    public static function isCallback(string $url, string $appUrl): bool
    {
        return $url === rtrim($appUrl, '/').self::CALLBACK_PATH;
    }
}
