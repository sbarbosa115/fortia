<?php

namespace App\Chat\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** A question of the chat's draft (PRD §7.19). */
final class ChatDraftQuestionOutput
{
    /**
     * @param list<ChatChoiceOutput> $choices
     * @param list<string>|null      $columns
     * @param list<string>|null      $rows
     */
    public function __construct(
        #[OA\Property(description: 'The stored question\'s id, for a questionnaire being edited')]
        public readonly ?string $id,
        public readonly string $title,
        public readonly ?string $description,
        #[OA\Property(enum: ['radio', 'checkbox', 'select', 'text', 'range', 'table', 'file'])]
        public readonly string $type,
        public readonly array $choices,
        public readonly ?string $category,
        public readonly bool $required,
        public readonly ?int $min,
        public readonly ?int $max,
        #[OA\Property(description: 'table: its columns', type: 'array', items: new OA\Items(type: 'string'), nullable: true)]
        public readonly ?array $columns,
        #[OA\Property(description: 'table: its fixed rows (none: the respondent adds rows)', type: 'array', items: new OA\Items(type: 'string'), nullable: true)]
        public readonly ?array $rows,
        #[OA\Property(ref: new Model(type: ChatTemplateOutput::class), nullable: true)]
        public readonly ?ChatTemplateOutput $template,
    ) {
    }
}
