<?php

namespace App\Responses\UI\Http\Controller;

use App\Assignations\Application\Query\AssignationQueries;
use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Questionnaires\Application\Query\QuestionnaireView;
use App\Responses\Application\ChainStages;
use App\Responses\Application\Command\StartSession;
use App\Responses\Application\Query\SessionQueries;
use App\Responses\Domain\Error\SessionNotFound;
use App\Responses\UI\Http\PublicRateLimit;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\RespondentClaims;
use App\Shared\Domain\Error\NotFound;
use App\Shared\UI\Http\Output\Document\SessionOutput;
use App\Shared\UI\Http\Request\RespondentBearer;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.4 POST /questionnaire/{questionnaire_id}/session · P (optional respondent Bearer), Cap(responses) only on
 * root questionnaires. Bare. A questionnaire that is inactive, unknown or assigned to an organization is 404 — an
 * assigned one is answered through /a/{id}, whose respondent token lets it (and its chain's stages) start here.
 */
#[OA\Tag(name: 'Respondent sessions')]
final class StartSessionController
{
    public function __construct(
        private readonly QuestionnaireQueries $questionnaires,
        private readonly AssignationQueries $assignations,
        private readonly SessionQueries $sessions,
        private readonly PlanGate $gate,
        private readonly CommandBus $commands,
        private readonly RespondentBearer $bearer,
        private readonly PublicRateLimit $rateLimit,
    ) {
    }

    #[Route('/questionnaire/{questionnaireId}/session', name: 'api_session_start', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The new session (bare, without the envelope)', content: new Model(type: SessionOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED (an invalid respondent token)')]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED (responses), TOO_MANY_ATTEMPTS')]
    public function __invoke(string $questionnaireId, Request $request): JsonResponse
    {
        $this->rateLimit->consume($request);
        $id = RouteId::uuid($questionnaireId);
        $claims = $this->bearer->claims($request);

        $questionnaire = $this->questionnaires->find($id);
        if (null === $questionnaire || !$questionnaire->isActive()) {
            throw new NotFound('QUESTIONNAIRE_NOT_FOUND', 'The questionnaire does not exist.');
        }
        $binding = $this->bindingOf($claims, $questionnaire);
        $assigned = $this->assignations->findByQuestionnaire($id);
        if (null !== $assigned && ($binding['assignations_id'] ?? null) !== $assigned['assignations_id']) {
            throw new NotFound('QUESTIONNAIRE_NOT_FOUND', 'The questionnaire does not exist.');
        }
        if ($questionnaire->isRoot() && null === $binding) {
            $this->gate->capacityForAccount($questionnaire->customerId(), Features::RESPONSES);
        }

        $sessionId = (string) $this->commands->dispatch(null === $binding ? new StartSession($id) : new StartSession(
            $id,
            (string) $binding['assignations_id'],
            'follow_up' === $binding['type'] ? null : $claims?->organizationUserId,
            'follow_up' === $binding['type'] ? 'follow_up' : null,
        ));
        $session = $this->sessions->find($sessionId) ?? throw new SessionNotFound();

        return ApiResponse::bare(SessionOutput::fromArray($session->data));
    }

    /**
     * The assignation a respondent token binds this session to: the token's own, when it is for this questionnaire or
     * for the root of its chain.
     *
     * @return array<string, mixed>|null
     */
    private function bindingOf(?RespondentClaims $claims, QuestionnaireView $questionnaire): ?array
    {
        if (null === $claims) {
            return null;
        }
        $assignation = $this->assignations->find($claims->assignationsId);
        if (null === $assignation || !\in_array($assignation['questionnaire_id'], [$questionnaire->id(), ChainStages::rootIdOf($questionnaire)], true)) {
            return null;
        }

        return $assignation;
    }
}
