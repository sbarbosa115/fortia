<?php

namespace App\Chat\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * The questionnaire the chat is building (PRD §7.19), kept by the client and sent back with every turn: phases
 * basics → questions → ending → review.
 */
final class ChatDraftOutput
{
    /** @param list<ChatDraftQuestionOutput> $questions */
    public function __construct(
        #[OA\Property(description: 'Set when an existing questionnaire is being edited')]
        public readonly ?string $questionnaire_id,
        #[OA\Property(enum: ['basics', 'questions', 'ending', 'review'])]
        public readonly string $phase,
        public readonly ?string $title,
        #[OA\Property(enum: ['regular', 'diagnostic', 'chain'], nullable: true)]
        public readonly ?string $type,
        public readonly ?string $topic,
        public readonly ?string $description,
        public readonly ?bool $landing_page,
        public readonly ?bool $has_disclaimer,
        public readonly ?string $disclaimer,
        public readonly ?bool $capture_user_data,
        public readonly bool $basics_confirmed,
        public readonly array $questions,
        public readonly ChatEndingOutput $ending,
        #[OA\Property(description: 'Chains: the instructions that generate the next stage')]
        public readonly ?string $chain_prompt,
        /** @var list<string> */
        #[OA\Property(description: 'Free-text labels the user asked for ("AP-03")', type: 'array', items: new OA\Items(type: 'string'))]
        public readonly array $tags = [],
    ) {
    }
}
