<?php

namespace App\Billing\UI\Http\Web;

use App\Billing\Application\Port\GatewayUnavailable;
use App\Billing\Application\Port\SimulatedGateway;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * The fake payment gateway's own pages (PAYMENT_PROVIDER=fake, dev and tests): the hosted checkout, with "Pay" and
 * "Cancel", and the billing portal, which can also simulate a renewal. Paying queues signed webhook events that
 * reach POST /api/v1/checkout/webhook before the browser goes back to {ADMIN_URL}/profile/plans?checkout=success.
 * No real payment data is ever asked for. With PAYMENT_PROVIDER=stripe every page is 404.
 */
final class FakeGatewayController
{
    public function __construct(
        private readonly SimulatedGateway $gateway,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/fake-gateway/checkout/{id}', name: 'fake_gateway_checkout', methods: ['GET'])]
    public function checkout(string $id): Response
    {
        return $this->renderCheckout($id);
    }

    #[Route('/fake-gateway/checkout/{id}/pay', name: 'fake_gateway_checkout_pay', methods: ['POST'])]
    public function pay(string $id, Request $request): Response
    {
        $this->session($id);
        $code = trim((string) $request->request->get('promotion_code', ''));
        try {
            return new RedirectResponse($this->gateway->payCheckout($id, '' === $code ? null : $code));
        } catch (GatewayUnavailable $e) {
            return $this->renderCheckout($id, $e->getMessage(), $code, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/fake-gateway/checkout/{id}/cancel', name: 'fake_gateway_checkout_cancel', methods: ['POST'])]
    public function cancel(string $id): Response
    {
        $this->session($id);

        return new RedirectResponse($this->gateway->cancelCheckout($id));
    }

    #[Route('/fake-gateway/portal/{id}', name: 'fake_gateway_portal', methods: ['GET'])]
    public function portal(string $id): Response
    {
        $portal = $this->enabled() ? $this->gateway->portalSession($id) : null;
        if (null === $portal) {
            throw new NotFoundHttpException('No such portal session.');
        }

        return new Response($this->twig->render('billing/fake_gateway/portal.html.twig', ['portal' => $portal]));
    }

    #[Route('/fake-gateway/portal/{id}/renew/{subscriptionId}', name: 'fake_gateway_portal_renew', methods: ['POST'])]
    public function renew(string $id, string $subscriptionId): Response
    {
        $portal = $this->enabled() ? $this->gateway->portalSession($id) : null;
        if (null === $portal || !\in_array($subscriptionId, array_column($portal['subscriptions'], 'id'), true)) {
            throw new NotFoundHttpException('No such subscription.');
        }
        $this->gateway->renew($subscriptionId);

        return new RedirectResponse('/fake-gateway/portal/'.rawurlencode($id));
    }

    private function renderCheckout(string $id, ?string $error = null, string $code = '', int $status = Response::HTTP_OK): Response
    {
        $session = $this->session($id);

        return new Response($this->twig->render('billing/fake_gateway/checkout.html.twig', [
            'session' => $session,
            'error' => $error,
            'promotion_code' => $code,
        ]), $status);
    }

    /** @return array<string, mixed> */
    private function session(string $id): array
    {
        $session = $this->enabled() ? $this->gateway->checkoutSession($id) : null;

        return $session ?? throw new NotFoundHttpException('No such checkout.');
    }

    private function enabled(): bool
    {
        return $this->gateway->enabled();
    }
}
