<?php

namespace App\Questionnaires\UI\Http\Output;

use App\Shared\Domain\Document\QuestionnaireTags;
use App\Shared\Domain\Document\QuestionnaireType;
use App\Shared\UI\Http\Output\Document\OnCompletedOutput;
use OpenApi\Attributes as OA;

/** A row of GET /questionnaire (PRD §8.4): the questionnaire without its questions. */
final class QuestionnaireListItemOutput
{
    public function __construct(
        public readonly string $questionnaire_id,
        public readonly string $customer_id,
        #[OA\Property(description: '"ROOT" or the id of the chain\'s root questionnaire')]
        public readonly string $parent,
        public readonly ?string $origin_session_id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
        public readonly bool $is_active,
        public readonly ?OnCompletedOutput $on_completed,
        #[OA\Property(enum: ['active', 'inactive'])]
        public readonly string $status,
        public readonly bool $landing_page,
        public readonly bool $capture_user_data,
        public readonly int $question_count,
        public readonly bool $is_chain,
        public readonly ?string $slug,
        #[OA\Property(enum: QuestionnaireType::VALUES)]
        public readonly string $type,
        /** @var list<string> */
        #[OA\Property(description: 'The owner\'s free-text labels', type: 'array', items: new OA\Items(type: 'string'))]
        public readonly array $tags,
    ) {
    }

    /** @param array<string, mixed> $row a QuestionnaireListing row */
    public static function fromArray(array $row): self
    {
        return new self(
            (string) $row['questionnaire_id'],
            (string) $row['customer_id'],
            (string) $row['parent'],
            isset($row['origin_session_id']) ? (string) $row['origin_session_id'] : null,
            (string) $row['title'],
            isset($row['description']) ? (string) $row['description'] : null,
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            (bool) $row['is_active'],
            \is_array($row['on_completed'] ?? null) ? OnCompletedOutput::fromArray($row['on_completed']) : null,
            (string) $row['status'],
            (bool) $row['landing_page'],
            (bool) $row['capture_user_data'],
            (int) $row['question_count'],
            (bool) $row['is_chain'],
            isset($row['slug']) ? (string) $row['slug'] : null,
            (string) $row['type'],
            QuestionnaireTags::fromStored($row['tags'] ?? null),
        );
    }
}
