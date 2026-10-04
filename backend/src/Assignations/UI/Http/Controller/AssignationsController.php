<?php

namespace App\Assignations\UI\Http\Controller;

use App\Assignations\Application\Command\CreateAssignation;
use App\Assignations\Application\Command\DeleteAssignation;
use App\Assignations\Application\Command\UpdateAssignation;
use App\Assignations\Application\Query\AssignationDetails;
use App\Assignations\Domain\Error\AssignationNotFound;
use App\Assignations\UI\Http\Input\AssignationInput;
use App\Assignations\UI\Http\Output\AssignationCreatedOutput;
use App\Assignations\UI\Http\Output\AssignationListOutput;
use App\Assignations\UI\Http\Output\AssignationOutput;
use App\Assignations\UI\Http\Output\ProjectPaginationOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\Rejected;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.8 assignations: list, create (AG), read (public: the respondent page reads it), change and
 * delete (the owner's account or an Admin, with the console's write permission). Another account's assignation is 404.
 */
#[OA\Tag(name: 'Assignations')]
final class AssignationsController
{
    private const MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly AssignationDetails $details,
        private readonly string $frontendUrl,
    ) {
    }

    /** Newest first; an Admin sees every account's. `questionnaire_id` finds the assignation of a questionnaire. */
    #[Route('/assignations', name: 'api_assignations_list', methods: ['GET'])]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'page_size', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100))]
    #[OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['default', 'follow_up']))]
    #[OA\Parameter(name: 'questionnaire_id', in: 'query', description: 'Only the assignations of this questionnaire (the form checks the one-organization rule with it)', schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: '{assignations, pagination}', content: new Model(type: AssignationListOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR (type, page, page_size or questionnaire_id)')]
    public function list(Caller $caller, Request $request): JsonResponse
    {
        $page = self::positiveInt($request->query->get('page'), 1, 'page');
        $pageSize = min(self::MAX_PAGE_SIZE, self::positiveInt($request->query->get('page_size'), 20, 'page_size'));
        $type = $request->query->get('type');
        if (null !== $type && '' !== $type && !\in_array($type, ['default', 'follow_up'], true)) {
            throw new Rejected('VALIDATION_ERROR', 'type: The value must be default or follow_up.');
        }
        $questionnaireId = $request->query->get('questionnaire_id');
        $questionnaireId = null === $questionnaireId || '' === $questionnaireId ? null : RouteId::uuid((string) $questionnaireId);

        $result = $this->details->page($caller->isAdmin() ? null : $caller->customerId, '' === $type ? null : $type, $questionnaireId, $page, $pageSize);

        return ApiResponse::ok(new AssignationListOutput(
            array_map(AssignationOutput::of(...), $result['items']),
            ProjectPaginationOutput::of($page, $pageSize, $result['total']),
        ));
    }

    /** AG. */
    #[Route('/assignations', name: 'api_assignations_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: AssignationInput::class))]
    #[OA\Response(response: 201, description: 'The respondent link and the id', content: new Model(type: AssignationCreatedOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, AUDIENCE_MEMBER_NOT_IN_ORGANIZATION')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'ORGANIZATION_NOT_FOUND, QUESTIONNAIRE_NOT_FOUND')]
    public function create(Caller $caller, #[Payload(allowExtraFields: false, groups: ['Default', 'create'])] AssignationInput $input): JsonResponse
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
        $id = (string) $this->commands->dispatch(new CreateAssignation($caller, $input->fields()));

        return ApiResponse::created(new AssignationCreatedOutput(rtrim($this->frontendUrl, '/').'/a/'.$id, $id));
    }

    /**
     * Public (the respondent page). An anonymous caller does not see the description nor the answers; a console user sees only their account's (another's is 404).
     */
    #[Route('/assignations/{id}', name: 'api_assignations_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The enriched assignation', content: new Model(type: AssignationOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 404, description: 'ASSIGNATION_NOT_FOUND')]
    public function get(?Caller $caller, string $id): JsonResponse
    {
        $id = RouteId::uuid($id);
        $data = $this->details->find($id, null !== $caller);
        if (null === $data || (null !== $caller && !$caller->owns((string) $data['customer_id']))) {
            throw new AssignationNotFound($id);
        }

        return ApiResponse::ok(AssignationOutput::of($data));
    }

    /** Partial. `type` cannot change; `due_date: null` clears it; the organization stays while in a project. */
    #[Route('/assignations/{id}', name: 'api_assignations_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: AssignationInput::class))]
    #[OA\Response(response: 200, description: 'The enriched assignation', content: new Model(type: AssignationOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, INVALID_UUID, ASSIGNATION_IN_PROJECT, AUDIENCE_MEMBER_NOT_IN_ORGANIZATION')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'ASSIGNATION_NOT_FOUND, ORGANIZATION_NOT_FOUND, QUESTIONNAIRE_NOT_FOUND')]
    public function update(Caller $caller, string $id, #[Payload(allowExtraFields: false, groups: ['Default', 'update'])] AssignationInput $input): JsonResponse
    {
        $id = RouteId::uuid($id);
        self::assertCanWrite($caller);
        $this->commands->dispatch(new UpdateAssignation($caller, $id, $input->fields()));

        return ApiResponse::ok(AssignationOutput::of((array) $this->details->find($id, true)));
    }

    /** 204. The respondents' answers stay. */
    #[Route('/assignations/{id}', name: 'api_assignations_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'ASSIGNATION_NOT_FOUND')]
    public function delete(Caller $caller, string $id): Response
    {
        $id = RouteId::uuid($id);
        self::assertCanWrite($caller);
        $this->commands->dispatch(new DeleteAssignation($caller, $id));

        return ApiResponse::noContent();
    }

    public static function assertCanWrite(Caller $caller): void
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', "Your read-only role can't make changes.");
        }
    }

    private static function positiveInt(mixed $value, int $default, string $field): int
    {
        if (null === $value || '' === $value) {
            return $default;
        }
        if (!\is_string($value) || 1 !== preg_match('/^[1-9]\d{0,5}$/', $value)) {
            throw new Rejected('VALIDATION_ERROR', $field.': This value should be a positive integer.');
        }

        return (int) $value;
    }
}
