<?php

namespace App\Assignations\UI\Http\Controller;

use App\Assignations\Application\Command\StartRespondentSession;
use App\Assignations\UI\Http\Input\RespondentLoginInput;
use App\Assignations\UI\Http\Output\RespondentLoginOutput;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\UI\Http\Output\Document\FlowOutput;
use App\Shared\UI\Http\Output\Document\SessionOutput;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.8 POST /assignations/{id}/sessions · P, rate-limited (D24). The respondent login in the order of §7.11
 * (see StartRespondentSession). Bare: {token, questionnaire: session, flow}.
 */
#[OA\Tag(name: 'Assignations')]
final class RespondentLoginController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly SessionQueries $sessions,
        private readonly QuestionnaireQueries $questionnaires,
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
    ) {
    }

    #[Route('/assignations/{id}/sessions', name: 'api_assignations_login', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: RespondentLoginInput::class))]
    #[OA\Response(response: 200, description: 'Bare: {token, questionnaire, flow}', content: new Model(type: RespondentLoginOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, INVALID_UUID, MISSING_IDENTIFIER')]
    #[OA\Response(response: 403, description: 'USER_NOT_FOUND, NOT_IN_AUDIENCE')]
    #[OA\Response(response: 404, description: 'ASSIGNATION_NOT_FOUND, QUESTIONNAIRE_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'FOLLOW_UP_COMPLETED')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED (assignations, then responses), TOO_MANY_ATTEMPTS')]
    public function __invoke(string $id, Request $request, #[Payload(allowExtraFields: false)] RespondentLoginInput $input): JsonResponse
    {
        if (!$this->publicApiLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many requests. Please try again in a minute.');
        }
        /** @var array{token: string, session_id: string} $login */
        $login = $this->commands->dispatch(new StartRespondentSession(RouteId::uuid($id), $input->email, $input->phone));
        $session = $this->sessions->find($login['session_id']) ?? throw new NotFound('QUESTIONNAIRE_NOT_FOUND', 'The questionnaire does not exist.');
        $flow = $this->questionnaires->flowOf($session->questionnaireId());

        return ApiResponse::bare(new RespondentLoginOutput(
            $login['token'],
            SessionOutput::fromArray($session->data),
            null === $flow ? null : FlowOutput::fromArray($flow->data),
        ));
    }
}
