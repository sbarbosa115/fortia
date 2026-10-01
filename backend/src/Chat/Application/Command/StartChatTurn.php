<?php

namespace App\Chat\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * POST /chat (PRD §8.10): one turn of the assistant, as a `chat` job. The caller already passed the AG check; the
 * conversation, the draft and the queued changes come from the client, which keeps them (the
 * backend keeps no chat state, §7.19). Returns the job id.
 */
final class StartChatTurn
{
    /**
     * @param list<array{role: string, content: string, files?: list<array{filename: string, text: string}>}> $messages
     * @param array<string, mixed>|null                                                                       $draft
     * @param array{kind: string, id: string}|null                                                            $item
     * @param list<array<string, mixed>>|null                                                                 $pendingWrites
     */
    public function __construct(
        public readonly Caller $caller,
        public readonly array $messages,
        public readonly string $mode = 'create',
        public readonly ?array $draft = null,
        public readonly ?array $item = null,
        public readonly ?array $pendingWrites = null,
    ) {
    }
}
