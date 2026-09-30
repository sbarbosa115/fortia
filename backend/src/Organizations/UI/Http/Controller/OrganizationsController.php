<?php

namespace App\Organizations\UI\Http\Controller;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Organizations\Application\Command\DeleteOrganization;
use App\Organizations\Application\Command\SaveOrganization;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Organizations\Domain\Error\OrganizationNotFound;
use App\Organizations\UI\Http\Input\OrganizationInput;
use App\Organizations\UI\Http\Output\OrganizationListOutput;
use App\Organizations\UI\Http\Output\OrganizationOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.7 organizations and members. There is no GET /organizations/{id}: the console filters the listing.
 * D1: PUT and DELETE require the organization to be the caller's (another account's is 404) unless Admin, and write
 * permission (a read-only role gets 403). D2: DELETE removes the members, and is refused (409
 * ORGANIZATION_HAS_ASSIGNATIONS) while assignations or projects point at the organization.
 */
#[OA\Tag(name: 'Organizations')]
final class OrganizationsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly OrganizationQueries $organizations,
        private readonly PlanGate $gate,
    ) {
    }

    /** Bare. The caller's organizations with their members, newest first; an Admin sees every account's. */
    #[Route('/organizations', name: 'api_organizations_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Bare: {organizations}', content: new Model(type: OrganizationListOutput::class))]
    public function list(Caller $caller): JsonResponse
    {
        $rows = $this->organizations->listFor($caller->isAdmin() ? null : $caller->customerId);

        return ApiResponse::bare(new OrganizationListOutput(array_map(OrganizationOutput::of(...), $rows)));
    }

    /** AG, Cap(organizations). Counts one "organizations" (OrganizationCreated). */
    #[Route('/organizations', name: 'api_organizations_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: OrganizationInput::class))]
    #[OA\Response(response: 201, description: 'The organization with its members', content: new Model(type: OrganizationOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 409, description: 'DOMAIN_EMAIL_CONFLICT')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED')]
    public function create(Caller $caller, #[Payload(allowExtraFields: false, groups: ['Default', 'create'])] OrganizationInput $input): JsonResponse
    {
        self::assertAdminGroups($caller);
        $this->gate->capacity($caller, Features::ORGANIZATIONS);
        $id = (string) $this->commands->dispatch(SaveOrganization::create($caller, $input->fields()));

        return ApiResponse::created($this->present($id));
    }

    /** Partial (at least one field). organization_users, when sent, is reconciled: by id, then email, then name + phone. */
    #[Route('/organizations/{id}', name: 'api_organizations_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: OrganizationInput::class))]
    #[OA\Response(response: 200, description: 'The organization with its members', content: new Model(type: OrganizationOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'ORGANIZATION_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'DOMAIN_EMAIL_CONFLICT')]
    public function update(Caller $caller, string $id, #[Payload(allowExtraFields: false, groups: ['Default', 'update'])] OrganizationInput $input): JsonResponse
    {
        $id = RouteId::uuid($id);
        self::assertCanWrite($caller);
        $this->commands->dispatch(SaveOrganization::update($caller, $id, $input->fields()));

        return ApiResponse::ok($this->present($id));
    }

    /** 204. Deletes the members too (D2). Counts one "organizations" (OrganizationDeleted). */
    #[Route('/organizations/{id}', name: 'api_organizations_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'ORGANIZATION_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'ORGANIZATION_HAS_ASSIGNATIONS')]
    public function delete(Caller $caller, string $id): Response
    {
        $id = RouteId::uuid($id);
        self::assertCanWrite($caller);
        $this->commands->dispatch(new DeleteOrganization($caller, $id));

        return ApiResponse::noContent();
    }

    private function present(string $id): OrganizationOutput
    {
        $data = $this->organizations->find($id);
        if (null === $data) {
            throw new OrganizationNotFound();
        }

        return OrganizationOutput::of($data);
    }

    private static function assertAdminGroups(Caller $caller): void
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
    }

    private static function assertCanWrite(Caller $caller): void
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
    }
}
