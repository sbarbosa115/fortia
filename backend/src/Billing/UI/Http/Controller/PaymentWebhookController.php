<?php

namespace App\Billing\UI\Http\Controller;

use App\Billing\Application\Command\HandlePaymentEvent;
use App\Billing\Application\Port\InvalidWebhookSignature;
use App\Billing\Application\Port\PaymentGateway;
use App\Shared\Application\Bus\CommandBus;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /checkout/webhook (PRD §8.3): public, authenticated by the gateway's signature. Answers plain text: 401 for a
 * bad signature, 500 to make the gateway retry, 200 "OK" otherwise (a redelivered event too).
 */
#[OA\Tag(name: 'Billing')]
final class PaymentWebhookController
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CommandBus $commands,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/checkout/webhook', name: 'api_checkout_webhook', methods: ['POST'])]
    #[OA\RequestBody(description: 'The gateway\'s event, signed in the Stripe-Signature header', required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(response: 200, description: 'OK (text/plain)', content: new OA\MediaType(mediaType: 'text/plain', schema: new OA\Schema(type: 'string')))]
    #[OA\Response(response: 401, description: 'Bad signature (text/plain)')]
    #[OA\Response(response: 500, description: 'Not applied: the gateway retries (text/plain)')]
    public function __invoke(Request $request): Response
    {
        try {
            $event = $this->gateway->parseWebhook($request->getContent(), (string) $request->headers->get('Stripe-Signature', ''));
        } catch (InvalidWebhookSignature) {
            return self::text('Invalid signature', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->commands->dispatch(new HandlePaymentEvent($event));
        } catch (\Throwable $e) {
            $this->logger->error('Payment webhook {type} {id} failed: {message}', ['type' => $event->type, 'id' => $event->id, 'message' => $e->getMessage(), 'exception' => $e]);

            return self::text('Retry', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::text('OK', Response::HTTP_OK);
    }

    private static function text(string $body, int $status): Response
    {
        return new Response($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
