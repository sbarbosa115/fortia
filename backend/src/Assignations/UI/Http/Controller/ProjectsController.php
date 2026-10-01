<?php

namespace App\Assignations\UI\Http\Controller;

use App\Assignations\Application\Command\CreateProject;
use App\Assignations\Application\Command\DeleteProject;
use App\Assignations\Application\Command\UpdateProject;
use App\Assignations\Application\Query\ProjectListCriteria;
use App\Assignations\Application\Query\ProjectQueries;
use App\Assignations\Domain\Error\ProjectNotFound;
use App\Assignations\UI\Http\Input\ProjectInput;
use App\Assignations\UI\Http\Output\ProjectListOutput;
use App\Assignations\UI\Http\Output\ProjectOutput;
use App\Assignations\UI\Http\Output\ProjectPaginationOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
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
 * PRD §8.9 projects: groups of follow-up assignations of one organization, with the states of §7.12. Ownership:
 * the caller's account or an Admin; another account's project is 404. Writing needs the console's write permission
 * (a read-only role gets 403 FORBIDDEN, like D1 for organizations).
 */
#[OA\Tag(name: 'Projects')]
final class ProjectsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly ProjectQueries $projects,
    ) {
    }

    /** Newest first; an Admin sees every account's. */
    #[Route('/projects', name: 'api_projects_list', methods: ['GET'])]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'page_size', in: 'query', schema: new OA\Schema(type: 'integer', default: 10, maximum: 100))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'progress includes pending', schema: new OA\Schema(type: 'string', enum: ['review', 'progress', 'correction', 'overdue', 'approved']))]
    #[OA\Parameter(name: 'q', in: 'query', description: "Every word in the project's or its organization's name", schema: new OA\Schema(type: 'string', maxLength: 200))]
    #[OA\Response(response: 200, description: '{projects, pagination}', content: new Model(type: ProjectListOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_PROJECT_STATUS')]
    public function list(Caller $caller, Request $request): JsonResponse
    {
        $criteria = ProjectListCriteria::fromQuery($request->query->all(), $caller->isAdmin() ? null : $caller->customerId);
        $page = $this->projects->page($criteria);

        return ApiResponse::ok(new ProjectListOutput(
            array_map(ProjectOutput::of(...), $page['items']),
            ProjectPaginationOutput::of($criteria->page, $criteria->pageSize, $page['total']),
        ));
    }

    /** The enriched project, with the follow-ups that may join it (available_assignations). */
    #[Route('/projects/{id}', name: 'api_projects_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The enriched project', content: new Model(type: ProjectOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 404, description: 'PROJECT_NOT_FOUND')]
    public function get(Caller $caller, string $id): JsonResponse
    {
        return ApiResponse::ok($this->present($caller, RouteId::uuid($id)));
    }

    #[Route('/projects', name: 'api_projects_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: ProjectInput::class))]
    #[OA\Response(response: 201, description: 'The enriched project', content: new Model(type: ProjectOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, ASSIGNATION_ORGANIZATION_MISMATCH, ASSIGNATION_NOT_FOLLOW_UP')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'ORGANIZATION_NOT_FOUND, ASSIGNATION_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'ASSIGNATION_IN_OTHER_PROJECT')]
    public function create(Caller $caller, #[Payload(allowExtraFields: false, groups: ['Default', 'create'])] ProjectInput $input): JsonResponse
    {
        self::assertCanWrite($caller);
        $id = (string) $this->commands->dispatch(new CreateProject(
            $caller,
            strtolower((string) $input->organization_id),
            (string) $input->name,
            $input->description,
            (string) $input->due_date,
            $input->assignationIds(),
        ));

        return ApiResponse::created($this->present($caller, $id));
    }

    /** Partial. assignation_ids replaces the set; organization_id may only be sent unchanged. */
    #[Route('/projects/{id}', name: 'api_projects_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: ProjectInput::class))]
    #[OA\Response(response: 200, description: 'The enriched project', content: new Model(type: ProjectOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, INVALID_UUID, ASSIGNATION_ORGANIZATION_MISMATCH, ASSIGNATION_NOT_FOLLOW_UP')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'PROJECT_NOT_FOUND, ASSIGNATION_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'ASSIGNATION_IN_OTHER_PROJECT')]
    public function update(Caller $caller, string $id, #[Payload(allowExtraFields: false, groups: ['Default', 'update'])] ProjectInput $input): JsonResponse
    {
        $id = RouteId::uuid($id);
        self::assertCanWrite($caller);
        $this->commands->dispatch(new UpdateProject($caller, $id, $input->fields()));

        return ApiResponse::ok($this->present($caller, $id));
    }

    /** 204. Its assignations are unlinked, not deleted. */
    #[Route('/projects/{id}', name: 'api_projects_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'PROJECT_NOT_FOUND')]
    public function delete(Caller $caller, string $id): Response
    {
        $id = RouteId::uuid($id);
        self::assertCanWrite($caller);
        $this->commands->dispatch(new DeleteProject($caller, $id));

        return ApiResponse::noContent();
    }

    private function present(Caller $caller, string $id): ProjectOutput
    {
        $data = $this->projects->find($id);
        if (null === $data || !$caller->owns((string) $data['customer_id'])) {
            throw new ProjectNotFound();
        }

        return ProjectOutput::of($data);
    }

    private static function assertCanWrite(Caller $caller): void
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', "Your read-only role can't make changes.");
        }
    }
}
