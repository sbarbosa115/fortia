<?php

namespace App\Chat\Application\Job;

use App\Chat\Application\CallerPayload;
use App\Chat\Application\ChatTurn;
use App\Chat\Domain\AttachedFile;
use App\Chat\Domain\ChatDraft;
use App\Chat\Domain\WriteQueue;
use App\Identity\Application\Query\AccountQueries;
use App\Jobs\Application\JobHandler;
use App\Jobs\Application\JobProgress;

/**
 * A turn of the chat assistant (PRD §7.19, §11: generic worker), job type `chat`. Its result's `type` is `chat`,
 * `chat-questionnaire-created`, `chat-questionnaire-drafted` or `chat-questionnaire-approved`; see ChatTurn.
 */
final class ChatTurnJob implements JobHandler
{
    public const TYPE = 'chat';

    public function __construct(
        private readonly ChatTurn $turn,
        private readonly AccountQueries $accounts,
    ) {
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function handle(array $payload, JobProgress $progress): array
    {
        $caller = CallerPayload::caller((array) ($payload['caller'] ?? []));
        $messages = [];
        foreach ((array) ($payload['messages'] ?? []) as $message) {
            if (\is_array($message) && \is_string($message['content'] ?? null)) {
                $role = 'assistant' === ($message['role'] ?? null) ? 'assistant' : 'user';
                $messages[] = ['role' => $role, 'content' => $message['content'], 'files' => 'user' === $role ? AttachedFile::listFromArray($message['files'] ?? null) : []];
            }
        }
        $item = \is_array($payload['item'] ?? null) ? ['kind' => (string) ($payload['item']['kind'] ?? ''), 'id' => (string) ($payload['item']['id'] ?? '')] : null;
        $language = str_starts_with((string) ($this->accounts->find($caller->customerId)['language'] ?? 'es'), 'en') ? 'en' : 'es';

        return $this->turn->run(
            $caller,
            'draft' === ($payload['mode'] ?? null) ? 'draft' : 'create',
            $messages,
            ChatDraft::fromArray($payload['draft'] ?? null),
            $item,
            WriteQueue::fromArray($payload['pending_writes'] ?? []),
            $language,
        );
    }
}
