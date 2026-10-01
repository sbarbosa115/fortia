<?php

namespace App\Integrations\UI\Http\Controller;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Integrations\Application\Command\DeleteWebhook;
use App\Integrations\Application\Command\SaveWebhook;
use App\Integrations\Application\Query\IntegrationQueries;
use App\Integrations\Domain\Error\WebhookNotFound;
use App\Integrations\UI\Http\Input\WebhookInput;
use App\Integrations\UI\Http\Output\WebhookDeliveryOutput;
use App\Integrations\UI\Http\Output\WebhookOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.11 webhooks of the caller's account, and their delivery log (D19, an extension: GET …/deliveries). Writes
 * need the console's write permission (403 for a read-only role); another account's webhook is 404 WEBHOOK_NOT_FOUND.
 */
#[OA\Tag(name: 'Integrations')]
final class WebhooksController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly IntegrationQueries $integrations,
        private readonly PlanGate $gate,
    ) {
    }

    /** A, write permission, Feat(webhook). event_type and method default to questionnaire.completed and POST. */
    #[Route('/webhooks', name: 'api_webhooks_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: WebhookInput::class))]
    #[OA\Response(response: 201, description: 'The webhook', content: new Model(type: WebhookOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR (url not https, unknown event_type or method)')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only role)')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED (the plan does not include webhook)')]
    public function create(Caller $caller, #[Payload(allowExtraFields: false, groups: ['Default', 'create'])] WebhookInput $input): JsonResponse
    {
        self::assertCanWrite($caller);
        $this->gate->feature($caller, Features::WEBHOOK);
        $id = (string) $this->commands->dispatch(SaveWebhook::create($caller, $input->fields()));

        return ApiResponse::created($this->present($id));
    }

    /** A. The account's webhooks, oldest first. */
    #[Route('/webhooks', name: 'api_webhooks_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Webhooks', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: WebhookOutput::class))))]
    public function list(Caller $caller): JsonResponse
    {
        return ApiResponse::ok(array_map(WebhookOutput::of(...), $this->integrations->webhooks($caller->customerId)));
    }

    /** A, write permission, Own. Partial: only the fields sent change. */
    #[Route('/webhooks/{id}', name: 'api_webhooks_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: WebhookInput::class))]
    #[OA\Response(response: 200, description: 'The webhook', content: new Model(type: WebhookOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only role)')]
    #[OA\Response(response: 404, description: 'WEBHOOK_NOT_FOUND (also another account\'s)')]
    public function update(Caller $caller, string $id, #[Payload(allowExtraFields: false, groups: ['Default', 'update'])] WebhookInput $input): JsonResponse
    {
        $id = RouteId::uuid($id);
        self::assertCanWrite($caller);
        $this->commands->dispatch(SaveWebhook::update($caller, $id, $input->fields()));

        return ApiResponse::ok($this->present($id));
    }

    /** A, write permission, Own. 204; its delivery log goes too. */
    #[Route('/webhooks/{id}', name: 'api_webhooks_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only role)')]
    #[OA\Response(response: 404, description: 'WEBHOOK_NOT_FOUND (also another account\'s)')]
    public function delete(Caller $caller, string $id): Response
    {
        $id = RouteId::uuid($id);
        self::assertCanWrite($caller);
        $this->commands->dispatch(new DeleteWebhook($caller, $id));

        return ApiResponse::noContent();
    }

    /** A, Own. The latest 20 deliveries, newest first (D19: the delivery log). */
    #[Route('/webhooks/{id}/deliveries', name: 'api_webhooks_deliveries', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Deliveries', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: WebhookDeliveryOutput::class))))]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 404, description: 'WEBHOOK_NOT_FOUND (also another account\'s)')]
    public function deliveries(Caller $caller, string $id): JsonResponse
    {
        $id = RouteId::uuid($id);
        $this->present($id, $caller);

        return ApiResponse::ok(array_map(WebhookDeliveryOutput::of(...), $this->integrations->deliveries($id)));
    }

    private function present(string $id, ?Caller $caller = null): WebhookOutput
    {
        $webhook = $this->integrations->webhook($id);
        if (null === $webhook || (null !== $caller && !$caller->owns((string) $webhook['customer_id']))) {
            throw new WebhookNotFound();
        }

        return WebhookOutput::of($webhook);
    }

    private static function assertCanWrite(Caller $caller): void
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
    }
}
