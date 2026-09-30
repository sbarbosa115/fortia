<?php

namespace App\Responses\UI\Http\Controller;

use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Responses\Application\Query\SessionQueries;
use App\Responses\Domain\Error\SessionNotFound;
use App\Responses\Domain\Error\SessionResultsNotFound;
use App\Responses\UI\Http\Output\SessionChainOutput;
use App\Responses\UI\Http\PublicRateLimit;
use App\Shared\Application\Security\Caller;
use App\Shared\UI\Http\Output\Document\CtaOutput;
use App\Shared\UI\Http\Output\Document\DiagnosticResultOutput;
use App\Shared\UI\Http\Output\Document\ProductOutput;
use App\Shared\UI\Http\Output\Document\SessionOutput;
use App\Shared\UI\Http\Output\Document\SessionResultsOutput;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** PRD §8.4 GET /questionnaire/session/{session_id}/results (P) and …/chain (A, Own). */
#[OA\Tag(name: 'Respondent sessions')]
final class SessionResultsController
{
    public function __construct(
        private readonly SessionQueries $sessions,
        private readonly QuestionnaireQueries $questionnaires,
        private readonly PublicRateLimit $rateLimit,
    ) {
    }

    /** The results the respondent app shows at /session/{id}/results, with the flow's cta, layout and texts. */
    #[Route('/questionnaire/session/{sessionId}/results', name: 'api_session_results', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The results', content: new Model(type: SessionResultsOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 404, description: 'SESSION_RESULTS_NOT_FOUND')]
    public function results(string $sessionId, Request $request): JsonResponse
    {
        $this->rateLimit->consume($request);
        $id = RouteId::uuid($sessionId);
        $results = $this->sessions->resultsOf($id);
        $session = $this->sessions->find($id);
        if (null === $results || null === $session) {
            throw new SessionResultsNotFound();
        }
        $flowId = $session->data['flow_id'] ?? null;
        $flow = \is_string($flowId) ? $this->questionnaires->findFlow($flowId) : null;

        return ApiResponse::ok(new SessionResultsOutput(
            $session->id(),
            $session->customerId(),
            $session->questionnaireId(),
            CtaOutput::fromArray($flow?->cta()),
            $flow?->layout(),
            $flow?->resultCopy(),
            null === $results['products'] ? null : array_map(static fn (array $p): ProductOutput => ProductOutput::fromArray($p), $results['products']),
            $results['ai_team_profile'],
            null === $results['diagnostic'] ? null : DiagnosticResultOutput::fromArray($results['diagnostic']),
            $results['extra'],
        ));
    }

    /** Every stage of the chain the respondent went through, for the account's answer detail. */
    #[Route('/questionnaire/session/{sessionId}/chain', name: 'api_session_chain', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The stages', content: new Model(type: SessionChainOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    #[OA\Response(response: 404, description: 'SESSION_NOT_FOUND (also another account\'s session)')]
    public function chain(string $sessionId, Caller $caller): JsonResponse
    {
        $id = RouteId::uuid($sessionId);
        $session = $this->sessions->find($id);
        if (null === $session || !$caller->owns($session->customerId())) {
            throw new SessionNotFound();
        }
        $chain = $this->sessions->chainOf($id) ?? throw new SessionNotFound();

        return ApiResponse::ok(new SessionChainOutput(
            array_map(static fn ($stage): SessionOutput => SessionOutput::fromArray($stage->data), $chain['stages']),
            $chain['total_stages'],
        ));
    }
}
