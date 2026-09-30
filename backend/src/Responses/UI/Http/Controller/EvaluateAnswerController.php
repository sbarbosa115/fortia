<?php

namespace App\Responses\UI\Http\Controller;

use App\Jobs\Application\Query\JobQueries;
use App\Responses\Application\Command\EvaluateAnswer;
use App\Responses\UI\Http\Input\EvaluateAnswerInput;
use App\Responses\UI\Http\PublicRateLimit;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RespondentBearer;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.4 POST /questionnaire/session/{session_id}/answers/{question_id}/evaluate · P. Starts the AI evaluation of
 * an answer (§7.9); the job's result is {type: "evaluation", status: "success" | "not_sense", question}.
 */
#[OA\Tag(name: 'Respondent sessions')]
final class EvaluateAnswerController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly JobQueries $jobs,
        private readonly RespondentBearer $bearer,
        private readonly PublicRateLimit $rateLimit,
    ) {
    }

    #[Route('/questionnaire/session/{sessionId}/answers/{questionId}/evaluate', name: 'api_session_evaluate', methods: ['POST'])]
    #[OA\Response(response: 202, description: 'The evaluation job', content: new OA\JsonContent(ref: '#/components/schemas/JobEnvelopeOutput'))]
    #[OA\Response(response: 400, description: 'INVALID_UUID, VALIDATION_ERROR')]
    #[OA\Response(response: 404, description: 'SESSION_NOT_FOUND, QUESTION_NOT_FOUND')]
    public function __invoke(string $sessionId, string $questionId, #[Payload] EvaluateAnswerInput $input, Request $request): JsonResponse
    {
        $this->rateLimit->consume($request);
        $jobId = (string) $this->commands->dispatch(new EvaluateAnswer(
            RouteId::uuid($sessionId),
            $questionId,
            ['options' => (array) $input->options],
            $this->bearer->claims($request),
        ));

        return ApiResponse::accepted(['job' => $this->jobs->find($jobId)], 'Evaluating');
    }
}
