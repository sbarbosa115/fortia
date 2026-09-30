<?php

namespace App\Questionnaires\UI\Http\Controller;

use App\Questionnaires\Application\Query\QuestionnaireDetails;
use App\Questionnaires\Domain\Error\FlowNotFound;
use App\Questionnaires\UI\Http\Output\QuestionnaireUrlOutput;
use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\UI\Http\Output\Document\FlowOutput;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The public flow lookups (PRD §8.4): the respondent app opens /f/{id|slug} with GET /flow/{identifier}, and the
 * store widget finds its questionnaire with GET /questionnaire/find?url=. Both are rate limited per IP (D24).
 */
#[OA\Tag(name: 'Flows')]
final class FlowController
{
    public function __construct(
        private readonly QuestionnaireDetails $details,
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
        private readonly string $frontendUrl,
    ) {
    }

    #[Route('/flow/{identifier}', name: 'api_flow_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The flow', content: new Model(type: FlowOutput::class))]
    #[OA\Response(response: 404, description: 'FLOW_NOT_FOUND (also when the questionnaire is assigned and was looked up by slug or flow id)')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function flow(string $identifier, Request $request): JsonResponse
    {
        $this->limit($request);
        $flow = $this->details->publicFlow($identifier) ?? throw new FlowNotFound();

        return ApiResponse::ok(FlowOutput::fromArray($flow->data));
    }

    #[Route('/questionnaire/find', name: 'api_questionnaire_find', methods: ['GET'], priority: 10)]
    #[OA\Parameter(name: 'url', in: 'query', required: true, description: 'The store page the widget is on', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Bare JSON: the respondent link of the matching flow', content: new Model(type: QuestionnaireUrlOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_REQUEST (url is missing)')]
    #[OA\Response(response: 404, description: 'FLOW_NOT_FOUND')]
    public function find(Request $request): JsonResponse
    {
        $this->limit($request);
        $url = $request->query->get('url');
        if (!\is_string($url) || '' === trim($url)) {
            throw new Rejected('INVALID_REQUEST', 'The url parameter is required.');
        }
        $flowId = $this->details->flowIdForStorePage($url) ?? throw new FlowNotFound();

        return ApiResponse::bare(new QuestionnaireUrlOutput(rtrim($this->frontendUrl, '/').'/f/'.$flowId));
    }

    private function limit(Request $request): void
    {
        if (!$this->publicApiLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many requests. Please try again in a minute.');
        }
    }
}
