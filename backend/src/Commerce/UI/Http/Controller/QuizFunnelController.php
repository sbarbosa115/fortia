<?php

namespace App\Commerce\UI\Http\Controller;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Commerce\Application\Command\CreateQuizFunnel;
use App\Commerce\Application\Command\ScrapeProducts;
use App\Commerce\UI\Http\Input\QuizFunnelInput;
use App\Commerce\UI\Http\Input\ScrapeInput;
use App\Jobs\Application\Query\JobQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The quiz funnel's two jobs (PRD §7.17, §10.5 Quiz Funnel): scraping a store's products and creating the quiz
 * funnel. Both answer 202 {job}; the console polls them every 5 s for up to 5 min (§11).
 */
#[OA\Tag(name: 'Quiz funnel')]
final class QuizFunnelController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly JobQueries $jobs,
        private readonly PlanGate $gate,
    ) {
    }

    /**
     * A. Job `scrape_products` (stage scraping). Result {type: "scrape_products", products}; fails with
     * CATALOG_UNREACHABLE or NO_PRODUCTS_FOUND. Persists nothing.
     */
    #[Route('/scrapers/products', name: 'api_scrapers_products', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: ScrapeInput::class))]
    #[OA\Response(response: 202, description: 'The scraping job', content: new OA\JsonContent(ref: '#/components/schemas/JobEnvelopeOutput'))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    public function scrape(Caller $caller, #[Payload(allowExtraFields: false)] ScrapeInput $input): JsonResponse
    {
        $jobId = (string) $this->commands->dispatch(new ScrapeProducts($caller->customerId, $input->url(), $input->limit()));

        return ApiResponse::accepted(['job' => $this->jobs->find($jobId)], 'Scraping products');
    }

    /**
     * AG, Cap(quiz-funnel). Job `create_quiz_funnel` (stages loading_products → saving_products →
     * generating_questionnaire → saving). Result {type: "create_quiz_funnel", flow: {id, slug, questionnaire_id},
     * questionnaire_url}. Without source_url the connected store is used (400 SHOPIFY_NOT_CONNECTED without one).
     */
    #[Route('/questionnaire/quiz-funnel', name: 'api_questionnaire_quiz_funnel', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: QuizFunnelInput::class))]
    #[OA\Response(response: 202, description: 'The quiz funnel job', content: new OA\JsonContent(ref: '#/components/schemas/JobEnvelopeOutput'))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, SHOPIFY_NOT_CONNECTED')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED')]
    public function create(Caller $caller, #[Payload(allowExtraFields: false)] QuizFunnelInput $input): JsonResponse
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
        $this->gate->capacity($caller, Features::QUIZ_FUNNEL);
        $jobId = (string) $this->commands->dispatch(new CreateQuizFunnel($caller->customerId, (string) $input->type, $input->storeUrl(), $input->products()));

        return ApiResponse::accepted(['job' => $this->jobs->find($jobId)], 'Creating the quiz funnel');
    }
}
