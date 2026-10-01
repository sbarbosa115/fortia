<?php

namespace App\Generation\UI\Http\Controller;

use App\Generation\Application\Command\RequestLinkedinQuestionnaire;
use App\Generation\Application\Command\RequestPromptStage;
use App\Generation\Domain\LinkedinRequest;
use App\Generation\UI\Http\Input\LinkedinInput;
use App\Generation\UI\Http\Input\PromptStageInput;
use App\Jobs\Application\Query\JobQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.4 "Flows and generation": the next stage of a prompt chain (§7.8, §9.11) and a diagnostic from a LinkedIn
 * profile (§7.18). Both are public (P), rate limited per client IP (D4), and answer 202 {job}; the job's result has
 * the questionnaire_id.
 */
#[OA\Tag(name: 'Flows and generation')]
final class GenerationController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly JobQueries $jobs,
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
    ) {
    }

    /**
     * P. Job `prompt_questionnaire`; result {type: "prompt_questionnaire", questionnaire_id} (the generated stage, a
     * child of the chain's root with origin_session_id = session_id).
     */
    #[Route('/questionnaire/prompt', name: 'api_questionnaire_prompt', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: PromptStageInput::class))]
    #[OA\Response(response: 202, description: 'The generation job', content: new OA\JsonContent(ref: '#/components/schemas/JobEnvelopeOutput'))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND, SESSION_NOT_FOUND, PROMPT_NOT_FOUND (no further stage to generate)')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function prompt(#[Payload(allowExtraFields: false)] PromptStageInput $input, Request $request): JsonResponse
    {
        $this->rateLimit($request, 'prompt');
        $jobId = (string) $this->commands->dispatch(new RequestPromptStage((string) $input->questionnaire_id, $input->answers(), $input->session_id));

        return ApiResponse::accepted(['job' => $this->jobs->find($jobId)], 'Generating the next stage');
    }

    /**
     * P. Job `linkedin_questionnaire`; result {type: "linkedin_questionnaire", questionnaire_id}, a diagnostic owned
     * by the configured account (LINKEDIN_OWNER_CUSTOMER_ID).
     */
    #[Route('/questionnaire/linkedin', name: 'api_questionnaire_linkedin', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: LinkedinInput::class))]
    #[OA\Response(response: 202, description: 'The generation job', content: new OA\JsonContent(ref: '#/components/schemas/JobEnvelopeOutput'))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    #[OA\Response(response: 503, description: 'LINKEDIN_UNAVAILABLE (no owner account configured)')]
    public function linkedin(#[Payload(allowExtraFields: false)] LinkedinInput $input, Request $request): JsonResponse
    {
        $this->rateLimit($request, 'linkedin');
        $jobId = (string) $this->commands->dispatch(new RequestLinkedinQuestionnaire(
            trim((string) $input->linkedin_url),
            LinkedinRequest::language($input->language),
        ));

        return ApiResponse::accepted(['job' => $this->jobs->find($jobId)], 'Generating the questionnaire');
    }

    private function rateLimit(Request $request, string $what): void
    {
        if (!$this->publicApiLimiter->create('generation-'.$what.'|'.$request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many requests. Please try again in a minute.');
        }
    }
}
