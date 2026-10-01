<?php

namespace App\Commerce\UI\Http\Web;

use App\Commerce\Domain\ShopDomain;
use App\Commerce\UI\Http\Controller\ShopifyController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * The fake e-commerce platform's authorization page (COMMERCE_PROVIDER=fake, dev and tests), so connecting a store
 * runs offline: "Install app" goes back to our callback with a code the fake platform accepts, exactly like the
 * real platform does. It only ever redirects to our own callback. With COMMERCE_PROVIDER=shopify every page is 404.
 */
final class FakeShopifyController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $appUrl,
        #[Autowire('%env(COMMERCE_PROVIDER)%')]
        private readonly string $provider,
    ) {
    }

    #[Route('/fake-shopify/admin/oauth/authorize', name: 'fake_shopify_authorize', methods: ['GET'])]
    public function authorize(Request $request): Response
    {
        $shop = (string) $request->query->get('shop', '');
        $state = (string) $request->query->get('state', '');
        $redirectUri = (string) $request->query->get('redirect_uri', '');
        if ('shopify' === $this->provider || !ShopDomain::isValid($shop) || '' === $state || !ShopifyController::isCallback($redirectUri, $this->appUrl)) {
            throw new NotFoundHttpException('No such authorization request.');
        }
        $install = $redirectUri.'?'.http_build_query(['code' => 'fake-'.bin2hex(random_bytes(12)), 'shop' => $shop, 'state' => $state]);

        return new Response($this->twig->render('commerce/fake_shopify_authorize.html.twig', [
            'shop' => $shop,
            'scope' => (string) $request->query->get('scope', 'read_products'),
            'install_url' => $install,
            'cancel_url' => '/fake-shopify/cancelled',
        ]));
    }

    #[Route('/fake-shopify/cancelled', name: 'fake_shopify_cancelled', methods: ['GET'])]
    public function cancelled(): Response
    {
        if ('shopify' === $this->provider) {
            throw new NotFoundHttpException();
        }

        return new Response($this->twig->render('commerce/shopify_connected.html.twig', ['shop' => '', 'cancelled' => true]));
    }
}
