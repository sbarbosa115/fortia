<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\Command\DescribeWorkspace;
use App\Identity\UI\Http\Input\WorkspaceInput;
use App\Identity\UI\Http\Output\WorkspaceOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** D15: onboarding step 2 saves the workspace (name, account language, website). No plan gate (PRD §7.1). */
#[OA\Tag(name: 'Account')]
final class WorkspaceController
{
    public function __construct(private readonly CommandBus $commands)
    {
    }

    #[Route('/customer/workspace', name: 'api_workspace_patch', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'The workspace', content: new Model(type: WorkspaceOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    public function __invoke(Caller $caller, #[Payload(allowExtraFields: false)] WorkspaceInput $input): JsonResponse
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', "Your read-only role can't make changes.");
        }

        /** @var array{name: string|null, language: string, website: string|null} $workspace */
        $workspace = $this->commands->dispatch(new DescribeWorkspace($caller->customerId, $input->provided()));

        return ApiResponse::ok(new WorkspaceOutput($workspace['name'], $workspace['language'], $workspace['website']), 'Workspace saved.');
    }
}
