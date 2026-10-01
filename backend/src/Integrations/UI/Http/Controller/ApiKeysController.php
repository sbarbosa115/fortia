<?php

namespace App\Integrations\UI\Http\Controller;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Integrations\Application\Command\CreateApiKey;
use App\Integrations\Application\Command\RevokeApiKey;
use App\Integrations\Application\Query\IntegrationQueries;
use App\Integrations\Domain\Error\ApiKeyNotFound;
use App\Integrations\UI\Http\Input\ApiKeyInput;
use App\Integrations\UI\Http\Output\ApiKeyCreatedOutput;
use App\Integrations\UI\Http\Output\ApiKeyOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.11 API keys of the caller's account. Creating and revoking need the console's write permission (a
 * read-only role gets 403, as the Integrations screen disables them, §10.17); creating also needs the plan to include
 * "api" (a feature gate: an exhausted quota does not block key management).
 */
#[OA\Tag(name: 'Integrations')]
final class ApiKeysController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly IntegrationQueries $integrations,
        private readonly PlanGate $gate,
    ) {
    }

    /** A, write permission, Feat(api). The plaintext key is in this response only. */
    #[Route('/api-keys', name: 'api_api_keys_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: ApiKeyInput::class))]
    #[OA\Response(response: 201, description: 'The new key, shown only once', content: new Model(type: ApiKeyCreatedOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only role)')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED (the plan does not include api)')]
    public function create(Caller $caller, #[Payload(allowExtraFields: false)] ApiKeyInput $input): JsonResponse
    {
        self::assertCanWrite($caller);
        $this->gate->feature($caller, Features::API);
        $key = (string) $this->commands->dispatch(new CreateApiKey($caller, (string) $input->name, $input->expiration_days));

        return ApiResponse::created(new ApiKeyCreatedOutput($key));
    }

    /** A. The account's active keys, newest first, without their secret. */
    #[Route('/api-keys', name: 'api_api_keys_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Active keys', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ApiKeyOutput::class))))]
    public function list(Caller $caller): JsonResponse
    {
        return ApiResponse::ok(array_map(ApiKeyOutput::of(...), $this->integrations->apiKeys($caller->customerId)));
    }

    /** A, write permission, Own. Revokes (204); the key stops working at once. */
    #[Route('/api-keys/{id}', name: 'api_api_keys_revoke', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Revoked')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (read-only role)')]
    #[OA\Response(response: 404, description: 'API_KEY_NOT_FOUND (also another account\'s or an already revoked key)')]
    public function revoke(Caller $caller, string $id): Response
    {
        self::assertCanWrite($caller);
        if (1 !== preg_match('/^[0-9a-f]{64}$/', $id)) {
            throw new ApiKeyNotFound();
        }
        $this->commands->dispatch(new RevokeApiKey($caller, $id));

        return ApiResponse::noContent();
    }

    private static function assertCanWrite(Caller $caller): void
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
    }
}
