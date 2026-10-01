<?php

namespace App\Chat\Application\Command;

use App\Chat\Application\CallerPayload;
use App\Chat\Application\Job\ChatTurnJob;
use App\Jobs\Application\Jobs;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class StartChatTurnHandler
{
    public function __construct(private readonly Jobs $jobs)
    {
    }

    public function __invoke(StartChatTurn $command): string
    {
        return $this->jobs->start(ChatTurnJob::TYPE, [
            'caller' => CallerPayload::of($command->caller),
            'mode' => $command->mode,
            'messages' => $command->messages,
            'draft' => $command->draft,
            'item' => $command->item,
            'pending_writes' => $command->pendingWrites ?? [],
        ], $command->caller->customerId);
    }
}
