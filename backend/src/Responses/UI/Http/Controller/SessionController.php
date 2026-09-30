<?php

namespace App\Responses\UI\Http\Controller;

use App\Jobs\Application\Query\JobQueries;
use App\Responses\Application\Command\SaveSession;
use App\Responses\Application\Command\SubmitOutcome;
use App\Responses\Application\Command\SubmitSession;
use App\Responses\Application\Query\SessionQueries;
use App\Responses\Domain\Error\SessionNotFound;
use App\Responses\UI\Http\Input\SessionInput;
use App\Responses\UI\Http\Output\SessionSubmissionOutput;
use App\Responses\UI\Http\PublicRateLimit;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\UI\Http\Output\Document\SessionOutput;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RespondentBearer;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.4 PUT and POST /questionnaire/session · P; an assignation's session needs its respondent Bearer (401
 * otherwise, D7). The body is the full session; only its values and user_data are read.
 */
#[OA\Tag(name: 'Respondent sessions')]
final class SessionController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly SessionQueries $sessions,
        private readonly JobQueries $jobs,
        private readonly RespondentBearer $bearer,
        private readonly PublicRateLimit $rateLimit,
    ) {
    }

    /** Saves progress. In a follow-up's shared session the members' answers merge (§7.11). */
    #[Route('/questionnaire/session', name: 'api_session_save', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'The session as saved', content: new Model(type: SessionOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    #[OA\Response(response: 404, description: 'SESSION_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'FOLLOW_UP_COMPLETED')]
    public function save(#[Payload] SessionInput $input, Request $request): JsonResponse
    {
        $this->rateLimit->consume($request);
        $sessionId = strtolower((string) $input->session_id);
        $this->commands->dispatch(new SaveSession($sessionId, (array) $input->questions, $this->bearer->claims($request)));
        $session = $this->sessions->find($sessionId) ?? throw new SessionNotFound();

        return ApiResponse::ok(SessionOutput::fromArray($session->data), 'Session saved');
    }

    /**
     * Submits the session and runs PRD §7.7. A quiz funnel answers 202 {job} (its result has the products); every
     * other type answers {type, …result, cta?, layout?, result_copy?}.
     */
    #[Route('/questionnaire/session', name: 'api_session_submit', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The result', content: new Model(type: SessionSubmissionOutput::class))]
    #[OA\Response(response: 202, description: 'The job computing the result (quiz funnel)', content: new OA\JsonContent(ref: '#/components/schemas/JobEnvelopeOutput'))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    #[OA\Response(response: 404, description: 'SESSION_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'FOLLOW_UP_COMPLETED')]
    public function submit(#[Payload] SessionInput $input, Request $request): JsonResponse
    {
        $this->rateLimit->consume($request);
        /** @var SubmitOutcome $outcome */
        $outcome = $this->commands->dispatch(new SubmitSession(
            strtolower((string) $input->session_id),
            (array) $input->questions,
            $input->user_data,
            $this->bearer->claims($request),
        ));

        if (null !== $outcome->jobId) {
            return ApiResponse::accepted(['job' => $this->jobs->find($outcome->jobId)], 'Processing');
        }

        return ApiResponse::ok($outcome->result, 'Session submitted');
    }
}
