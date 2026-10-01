<?php

namespace App\Chat\UI\Http\Output;

use OpenApi\Attributes as OA;

/** The result of a `chat` job (PRD §7.19 "Job result"). */
final class ChatTurnResultOutput
{
    /**
     * @param list<string>                 $quick_replies
     * @param list<ChatActionOutput>       $actions
     * @param list<ChatPendingWriteOutput> $pending_writes
     * @param array<string, mixed>|null    $flow
     */
    public function __construct(
        #[OA\Property(enum: ['chat', 'chat-questionnaire-created', 'chat-questionnaire-drafted', 'chat-questionnaire-approved'])]
        public readonly string $type,
        #[OA\Property(description: 'The assistant\'s answer, in Markdown; records are linked as [Name](item:<kind>/<id>)')]
        public readonly string $message,
        public readonly array $quick_replies,
        public readonly ?ChatDraftOutput $draft,
        public readonly array $actions,
        public readonly array $pending_writes,
        #[OA\Property(description: 'chat-questionnaire-created: the questionnaire saved')]
        public readonly ?string $questionnaire_id = null,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: true, description: 'chat-questionnaire-approved: the approved draft as the body of POST /questionnaire (states, cta, layout); the slug is the caller\'s')]
        public readonly ?array $flow = null,
    ) {
    }
}
