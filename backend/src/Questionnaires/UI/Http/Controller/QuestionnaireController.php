<?php

namespace App\Questionnaires\UI\Http\Controller;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Questionnaires\Application\Command\CopyQuestionnaire;
use App\Questionnaires\Application\Command\SaveFlow;
use App\Questionnaires\Application\Command\SetQuestionnaireActive;
use App\Questionnaires\Application\Query\ListingCriteria;
use App\Questionnaires\Application\Query\QuestionnaireDetails;
use App\Questionnaires\Application\Query\QuestionnaireListing;
use App\Questionnaires\Domain\Error\QuestionnaireNotFound;
use App\Questionnaires\Domain\Flow\FlowDraft;
use App\Questionnaires\UI\Http\Input\ActiveInput;
use App\Questionnaires\UI\Http\Input\FlowInput;
use App\Questionnaires\UI\Http\Input\UpdateFlowInput;
use App\Questionnaires\UI\Http\Output\PromptListOutput;
use App\Questionnaires\UI\Http\Output\PromptOutput;
use App\Questionnaires\UI\Http\Output\QuestionnaireIdOutput;
use App\Questionnaires\UI\Http\Output\QuestionnaireListItemOutput;
use App\Questionnaires\UI\Http\Output\QuestionnaireListOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Output\Document\QuestionnaireOutput;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The console's questionnaires (PRD §8.4): the listing, creating and editing from a flow, reading one, the Active
 * toggle, copies and a chain's prompts. Order of every write (§5 A2): the caller → the payload and the flow rules →
 * the plan gate → the command. Another account's questionnaire is 404, never 403.
 */
#[OA\Tag(name: 'Questionnaires')]
final class QuestionnaireController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QuestionnaireDetails $details,
        private readonly QuestionnaireListing $listing,
        private readonly PlanGate $gate,
    ) {
    }

    #[Route('/questionnaire', name: 'api_questionnaire_list', methods: ['GET'])]
    #[OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ListingCriteria::TYPES))]
    #[OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ListingCriteria::SORTS))]
    #[OA\Parameter(name: 'order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc']))]
    #[OA\Parameter(name: 'is_active', in: 'query', schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0']))]
    #[OA\Parameter(name: 'parent', in: 'query', description: 'ROOT (default) or a questionnaire id', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'page_size', in: 'query', schema: new OA\Schema(type: 'integer', maximum: 100))]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Every word must appear in the title', schema: new OA\Schema(type: 'string', maxLength: 200))]
    #[OA\Response(response: 200, description: 'One page of questionnaires', content: new Model(type: QuestionnaireListOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_TYPE, INVALID_SORT, INVALID_ORDER, INVALID_IS_ACTIVE')]
    public function list(Caller $caller, Request $request): JsonResponse
    {
        $criteria = ListingCriteria::fromQuery($request->query->all(), $caller->isAdmin() ? null : $caller->customerId);
        $page = $this->listing->page($criteria);

        return ApiResponse::ok(QuestionnaireListOutput::of($page['items'], $criteria->page, $criteria->pageSize, $page['total']));
    }

    #[Route('/questionnaire', name: 'api_questionnaire_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: FlowInput::class))]
    #[OA\Response(response: 201, description: 'Created', content: new Model(type: QuestionnaireIdOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 409, description: 'SLUG_ALREADY_IN_USE')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED')]
    public function create(Caller $caller, #[Payload(allowExtraFields: false)] FlowInput $input): JsonResponse
    {
        self::requireAdminGroups($caller);
        $draft = self::draft($input);
        $draft->assertPromptKeysBelongTo($caller->customerId);
        $this->gate->capacity($caller, Features::forQuestionnaireType($draft->usageType()));

        $id = (string) $this->commands->dispatch(new SaveFlow(
            $caller->customerId,
            (array) $input->states,
            $input->slug,
            $input->cta,
            $input->layout,
            $input->result_copy,
            detail: $input->detail,
        ));

        return ApiResponse::created(new QuestionnaireIdOutput($id));
    }

    #[Route('/questionnaire', name: 'api_questionnaire_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: UpdateFlowInput::class))]
    #[OA\Response(response: 200, description: 'Saved (data: null)')]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'QUESTIONNAIRE_ALREADY_ANSWERED, SLUG_ALREADY_IN_USE')]
    public function update(Caller $caller, #[Payload(allowExtraFields: false)] UpdateFlowInput $input): JsonResponse
    {
        self::requireAdminGroups($caller);
        $questionnaire = $this->owned($caller, RouteId::uuid((string) $input->questionnaire_id));
        $draft = self::draft($input);
        $draft->assertPromptKeysBelongTo((string) $questionnaire['customer_id']);

        $this->commands->dispatch(new SaveFlow(
            (string) $questionnaire['customer_id'],
            (array) $input->states,
            $input->slug,
            $input->cta,
            $input->layout,
            $input->result_copy,
            (string) $questionnaire['questionnaire_id'],
            $input->detail,
        ));

        return ApiResponse::ok(null, 'Saved');
    }

    #[Route('/questionnaire/{id}', name: 'api_questionnaire_get', methods: ['GET'], priority: -10)]
    #[OA\Response(response: 200, description: 'The questionnaire, with the diagnostic merged into on_completed', content: new Model(type: QuestionnaireOutput::class))]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    public function get(Caller $caller, string $id): JsonResponse
    {
        return ApiResponse::ok(QuestionnaireOutput::fromArray($this->owned($caller, RouteId::uuid($id))));
    }

    #[Route('/questionnaire/{id}', name: 'api_questionnaire_set_active', methods: ['PATCH'], priority: -10)]
    #[OA\RequestBody(content: new Model(type: ActiveInput::class))]
    #[OA\Response(response: 200, description: 'The updated row', content: new Model(type: QuestionnaireListItemOutput::class))]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    public function setActive(Caller $caller, string $id, #[Payload(allowExtraFields: false)] ActiveInput $input): JsonResponse
    {
        self::requireAdminGroups($caller);
        $id = (string) $this->owned($caller, RouteId::uuid($id))['questionnaire_id'];
        $this->commands->dispatch(new SetQuestionnaireActive($id, (bool) $input->is_active));

        return ApiResponse::ok(QuestionnaireListItemOutput::fromArray((array) $this->listing->row($id)));
    }

    #[Route('/questionnaire/{id}/copy', name: 'api_questionnaire_copy', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'The new questionnaire', content: new Model(type: QuestionnaireOutput::class))]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED')]
    public function copy(Caller $caller, string $id): JsonResponse
    {
        self::requireAdminGroups($caller);
        $id = (string) $this->owned($caller, RouteId::uuid($id))['questionnaire_id'];
        $this->gate->capacity($caller, $this->details->featureOf($id));

        $copyId = (string) $this->commands->dispatch(new CopyQuestionnaire($id));

        return ApiResponse::created(QuestionnaireOutput::fromArray((array) $this->details->find($copyId)));
    }

    #[Route('/questionnaire/{id}/prompts', name: 'api_questionnaire_prompts', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The chain\'s prompts in order, with their text', content: new Model(type: PromptListOutput::class))]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    public function prompts(Caller $caller, string $id): JsonResponse
    {
        self::requireAdminGroups($caller);
        $id = (string) $this->owned($caller, RouteId::uuid($id))['questionnaire_id'];

        return ApiResponse::ok(new PromptListOutput(array_map(
            static fn (array $p): PromptOutput => PromptOutput::fromArray($p),
            $this->details->prompts($id),
        )));
    }

    /**
     * The questionnaire, when the caller may see it (their account, or any as Admin).
     *
     * @return array<string, mixed>
     */
    private function owned(Caller $caller, string $id): array
    {
        $questionnaire = $this->details->find($id);
        if (null === $questionnaire || !$caller->owns((string) $questionnaire['customer_id'])) {
            throw new QuestionnaireNotFound();
        }

        return $questionnaire;
    }

    /** The "AG" endpoints of PRD §8.4. */
    private static function requireAdminGroups(Caller $caller): void
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
    }

    private static function draft(FlowInput $input): FlowDraft
    {
        return FlowDraft::parse((array) $input->states, $input->slug, $input->cta, $input->layout, $input->result_copy);
    }
}
