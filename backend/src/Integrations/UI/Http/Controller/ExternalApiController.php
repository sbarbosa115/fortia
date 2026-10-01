<?php

namespace App\Integrations\UI\Http\Controller;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Integrations\Application\ApiKeyAuthentication;
use App\Integrations\Application\Command\RecordApiCall;
use App\Integrations\Application\Port\ExternalCatalog;
use App\Integrations\UI\Http\Output\ExternalAnswersOutput;
use App\Integrations\UI\Http\Output\ExternalPaginationOutput;
use App\Integrations\UI\Http\Output\ExternalQuestionnaireListOutput;
use App\Integrations\UI\Http\Output\ExternalQuestionnaireOutput;
use App\Integrations\UI\Http\Output\ExternalSessionOutput;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The external API (PRD §8.11), authenticated with the X-API-Key header instead of a console user. Order (§5 A2):
 * the key (401 INVALID_API_KEY, the same whether missing, unknown, revoked or expired) → the query → Cap(api) on the
 * key's account (429) → the call is recorded (last_used_at + ApiUsage, which counts one "api") → the read. Rate
 * limited per IP like the other public endpoints (D24).
 */
#[OA\Tag(name: 'External API')]
final class ExternalApiController
{
    private const MAX_PAGE_SIZE = 50;

    public function __construct(
        private readonly ApiKeyAuthentication $authentication,
        private readonly ExternalCatalog $catalog,
        private readonly QuestionnaireQueries $questionnaires,
        private readonly SessionQueries $sessions,
        private readonly CommandBus $commands,
        private readonly PlanGate $gate,
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
    ) {
    }

    /** X-API-Key, Cap(api). The account's questionnaires, newest first; page_size default 50, at most 50. */
    #[Route('/external/questionnaires', name: 'api_external_questionnaires', methods: ['GET'])]
    #[OA\Parameter(name: 'X-API-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'page_size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 50, minimum: 1))]
    #[OA\Response(response: 200, description: 'Questionnaires', content: new Model(type: ExternalQuestionnaireListOutput::class))]
    #[OA\Response(response: 401, description: 'INVALID_API_KEY')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED, TOO_MANY_ATTEMPTS')]
    public function questionnaires(Request $request): JsonResponse
    {
        $key = $this->authenticate($request);
        [$page, $pageSize] = self::paging($request);
        $this->gate->capacityForAccount($key['customer_id'], Features::API);
        $this->commands->dispatch(new RecordApiCall($key['api_key_id'], 'GET /external/questionnaires'));

        $result = $this->catalog->questionnaires($key['customer_id'], $page, $pageSize);

        return ApiResponse::ok(new ExternalQuestionnaireListOutput(
            array_map(ExternalQuestionnaireOutput::of(...), $result['items']),
            ExternalPaginationOutput::of($page, $pageSize, $result['total']),
        ));
    }

    /** X-API-Key, Cap(api). A questionnaire's sessions with their answers (§7.14 format), newest first. */
    #[Route('/external/questionnaires/{id}/answers', name: 'api_external_answers', methods: ['GET'])]
    #[OA\Parameter(name: 'X-API-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'page_size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 50, minimum: 1))]
    #[OA\Response(response: 200, description: 'Sessions and their answers', content: new Model(type: ExternalAnswersOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 401, description: 'INVALID_API_KEY')]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND (also another account\'s)')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED, TOO_MANY_ATTEMPTS')]
    public function answers(Request $request, string $id): JsonResponse
    {
        $key = $this->authenticate($request);
        $id = RouteId::uuid($id);
        [$page, $pageSize] = self::paging($request);
        $this->gate->capacityForAccount($key['customer_id'], Features::API);
        $questionnaire = $this->questionnaires->find($id);
        if (null === $questionnaire || $questionnaire->customerId() !== $key['customer_id']) {
            throw new NotFound('QUESTIONNAIRE_NOT_FOUND', 'Questionnaire not found.');
        }
        $this->commands->dispatch(new RecordApiCall($key['api_key_id'], 'GET /external/questionnaires/{id}/answers'));

        $result = $this->catalog->sessionIds($id, $page, $pageSize);
        $sessions = [];
        foreach ($result['ids'] as $sessionId) {
            $sessions[] = new ExternalSessionOutput($sessionId, $this->sessions->answersOf($sessionId) ?? []);
        }

        return ApiResponse::ok(new ExternalAnswersOutput($id, $sessions, ExternalPaginationOutput::of($page, $pageSize, $result['total'])));
    }

    /** @return array{api_key_id: string, customer_id: string} */
    private function authenticate(Request $request): array
    {
        if (!$this->publicApiLimiter->create('external|'.$request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many requests. Please try again in a minute.');
        }
        $header = $request->headers->get('X-API-Key');

        return $this->authentication->authenticate(null === $header ? null : trim($header));
    }

    /**
     * page ≥ 1 (default 1) and page_size 1–50 (default 50); out-of-range or non-numeric values fall back to the
     * nearest allowed one.
     *
     * @return array{int, int}
     */
    private static function paging(Request $request): array
    {
        $page = filter_var($request->query->get('page'), \FILTER_VALIDATE_INT);
        $pageSize = filter_var($request->query->get('page_size'), \FILTER_VALIDATE_INT);

        return [
            false === $page ? 1 : max(1, $page),
            false === $pageSize ? self::MAX_PAGE_SIZE : max(1, min(self::MAX_PAGE_SIZE, $pageSize)),
        ];
    }
}
