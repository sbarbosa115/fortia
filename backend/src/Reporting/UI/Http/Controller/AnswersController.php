<?php

namespace App\Reporting\UI\Http\Controller;

use App\Reporting\Application\Query\AnswersQueries;
use App\Reporting\Application\Query\ReportedQuestionnaires;
use App\Reporting\Domain\Model\AnswersPage;
use App\Reporting\UI\Http\Output\AnswerDetailOutput;
use App\Reporting\UI\Http\Output\AnswersPageOutput;
use App\Shared\Application\Security\Caller;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Reporting')]
final class AnswersController
{
    public function __construct(
        private readonly ReportedQuestionnaires $questionnaires,
        private readonly AnswersQueries $answers,
    ) {
    }

    /**
     * The responses of a questionnaire, newest first, a cursor page at a time (PRD §8.4, §10.8). Any console user of
     * the account; another tenant's id is 404.
     */
    #[Route('/questionnaire/{id}/answers', name: 'api_questionnaire_answers', methods: ['GET'])]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', default: 'completed', enum: ['completed', 'filling', 'filled_out', 'processing', 'all', 'in_progress', 'submitted']))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 100, enum: [20, 50, 100]))]
    #[OA\Parameter(name: 'cursor', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'include_chain', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'assignations_id', in: 'query', description: 'Only the sessions of this assignation (the Sheets export of an assignation).', schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'A page of sessions', content: new Model(type: AnswersPageOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID, INVALID_REQUEST (status), INVALID_PAGE_SIZE, INVALID_CURSOR')]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    public function list(string $id, Caller $caller, Request $request): JsonResponse
    {
        $questionnaire = $this->questionnaires->get($caller, RouteId::uuid($id));
        $page = AnswersPage::of(
            self::query($request, 'status'),
            self::query($request, 'limit'),
            self::query($request, 'cursor'),
            null === self::query($request, 'assignations_id') ? null : RouteId::uuid((string) self::query($request, 'assignations_id')),
        );
        $includeChain = \in_array(self::query($request, 'include_chain'), ['true', '1'], true);

        return ApiResponse::ok(AnswersPageOutput::of($this->answers->page($questionnaire, $page, $includeChain)));
    }

    /**
     * One response of the questionnaire (or of one of its generated stages) with its results: the answer detail's
     * fallback when the session chain is not available (PRD §10.8).
     */
    #[Route('/questionnaire/{id}/answers/{sessionId}', name: 'api_questionnaire_answer', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The session and its results', content: new Model(type: AnswerDetailOutput::class))]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND, SESSION_NOT_FOUND')]
    public function detail(string $id, string $sessionId, Caller $caller): JsonResponse
    {
        $questionnaire = $this->questionnaires->get($caller, RouteId::uuid($id));

        return ApiResponse::ok(AnswerDetailOutput::of($this->answers->detail($questionnaire, RouteId::uuid($sessionId))));
    }

    private static function query(Request $request, string $name): ?string
    {
        $value = $request->query->all()[$name] ?? null;

        return \is_string($value) ? trim($value) : null;
    }
}
