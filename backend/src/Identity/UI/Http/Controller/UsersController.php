<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\Command\CreateTeamUser;
use App\Identity\Application\Query\AccountQueries;
use App\Identity\Domain\Error\InvalidRole;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\UI\Http\Input\CreateUserInput;
use App\Identity\UI\Http\Output\UserListOutput;
use App\Identity\UI\Http\Output\UserOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** PRD §8.2 the account's console users. No invitations, editing or deletion (§8.2 "Not available"). */
#[OA\Tag(name: 'Account')]
final class UsersController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AccountQueries $accounts,
    ) {
    }

    #[Route('/users', name: 'api_users_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Root first, then by name', content: new Model(type: UserListOutput::class))]
    public function list(Caller $caller): JsonResponse
    {
        return ApiResponse::ok(new UserListOutput(array_map(UserOutput::of(...), $this->accounts->usersOf($caller->customerId))));
    }

    /** AG: a user with a permanent password and an assignable role (UserCreated). */
    #[Route('/users', name: 'api_users_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'The user was created', content: new Model(type: UserOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_ROLE, VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 409, description: 'EMAIL_ALREADY_EXISTS')]
    public function create(Caller $caller, #[Payload(allowExtraFields: false)] CreateUserInput $input): JsonResponse
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
        if (!\in_array($input->role, Caller::ASSIGNABLE_ROLES, true)) {
            throw new InvalidRole();
        }

        /** @var array{email: string, name: string, root: bool, role: string, customer_id: string} $user */
        $user = $this->commands->dispatch(new CreateTeamUser(
            $caller->customerId,
            EmailAddress::normalize((string) $input->email),
            trim((string) $input->name),
            (string) $input->role,
            (string) $input->password,
        ));

        return ApiResponse::created(UserOutput::of($user), 'User created.');
    }
}
