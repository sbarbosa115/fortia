<?php

namespace App\Chat\UI\Http\Controller;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Chat\Application\Command\StartChatTurn;
use App\Chat\UI\Http\Input\ChatInput;
use App\Chat\UI\Http\Output\ChatJobEnvelopeOutput;
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
 * The AI assistant (PRD §8.10, §7.19): AG, Cap(chat). Each message is a turn that runs as a `chat` job (202 {job});
 * the console polls it every 2 s for up to 5 min (§11).
 */
#[OA\Tag(name: 'Chat')]
final class ChatController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly JobQueries $jobs,
        private readonly PlanGate $gate,
    ) {
    }

    #[Route('/chat', name: 'api_chat', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: ChatInput::class))]
    #[OA\Response(response: 202, description: 'The turn\'s job; its result is a ChatTurnResultOutput', content: new Model(type: ChatJobEnvelopeOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED')]
    public function __invoke(Caller $caller, #[Payload(allowExtraFields: false)] ChatInput $input): JsonResponse
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
        $this->gate->capacity($caller, Features::CHAT);

        $jobId = (string) $this->commands->dispatch(new StartChatTurn(
            $caller,
            $input->messages(),
            $input->mode ?? 'create',
            $input->draft,
            $input->item,
            $input->pending_writes,
        ));

        return ApiResponse::accepted(['job' => $this->jobs->find($jobId)], 'Thinking');
    }
}
