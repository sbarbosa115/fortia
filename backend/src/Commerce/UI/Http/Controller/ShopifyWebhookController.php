<?php

namespace App\Commerce\UI\Http\Controller;

use App\Commerce\Application\Port\CommercePlatform;
use App\Commerce\Domain\WebhookSignature;
use App\Shared\Domain\Error\Unauthenticated;
use App\Shared\UI\Http\Response\ApiResponse;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The e-commerce platform's mandatory GDPR webhooks (PRD §8.6, §13.10): customers/data_request, customers/redact and
 * shop/redact. P + HMAC: the raw body's HMAC-SHA256 (base64) must match X-Shopify-Hmac-Sha256 with the app secret,
 * else 401. Mappi keeps no data of the stores' customers, so they only log (shop and topic, never the payload) and
 * answer 200.
 */
#[OA\Tag(name: 'Shopify')]
final class ShopifyWebhookController
{
    private const TOPICS = ['customers/data_request', 'customers/redact', 'shop/redact'];

    public function __construct(
        private readonly CommercePlatform $platform,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/shopify/webhooks/{topic}', name: 'api_shopify_gdpr_webhook', requirements: ['topic' => 'customers/data_request|customers/redact|shop/redact'], methods: ['POST'])]
    #[OA\Parameter(name: 'X-Shopify-Hmac-Sha256', in: 'header', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Received')]
    #[OA\Response(response: 401, description: 'INVALID_SIGNATURE')]
    public function receive(string $topic, Request $request): JsonResponse
    {
        if (!\in_array($topic, self::TOPICS, true)
            || !WebhookSignature::isValid($request->getContent(), $request->headers->get('X-Shopify-Hmac-Sha256'), $this->platform->webhookSecret())) {
            throw new Unauthenticated('INVALID_SIGNATURE', 'The webhook signature is not valid.');
        }
        $this->logger->info('GDPR webhook {topic} received from {shop}', [
            'topic' => $topic,
            'shop' => (string) $request->headers->get('X-Shopify-Shop-Domain', ''),
        ]);

        return ApiResponse::ok(null, 'Received');
    }
}
