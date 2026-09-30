<?php

namespace App\Shared\UI\Http\Output\Document;

use App\Shared\Domain\Document\QuestionnaireType;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** A full questionnaire (PRD §6.5, §8.4 GET /questionnaire/{id}). */
final class QuestionnaireOutput
{
    /** @param list<QuestionOutput> $questions */
    public function __construct(
        public readonly string $questionnaire_id,
        public readonly string $customer_id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $disclaimer,
        public readonly bool $capture_user_data,
        public readonly bool $landing_page,
        #[OA\Property(enum: QuestionnaireType::VALUES)]
        public readonly string $type,
        public readonly bool $is_active,
        public readonly ?OnCompletedOutput $on_completed,
        #[OA\Property(description: '"ROOT" or the id of the chain\'s root questionnaire')]
        public readonly string $parent,
        public readonly ?string $origin_session_id,
        public readonly ?string $session_id,
        public readonly ?string $started_at,
        public readonly ?string $ended_at,
        public readonly int $question_count,
        public readonly bool $is_chain,
        public readonly ?string $slug,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: QuestionOutput::class)))]
        public readonly array $questions,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {
    }

    /** @param array<string, mixed> $q the QuestionnaireView data (PRD §6.5 shape) */
    public static function fromArray(array $q, bool $scoringVisible = true): self
    {
        $onCompleted = \is_array($q['on_completed'] ?? null) ? $q['on_completed'] : null;

        return new self(
            (string) $q['questionnaire_id'],
            (string) $q['customer_id'],
            (string) $q['title'],
            isset($q['description']) ? (string) $q['description'] : null,
            isset($q['disclaimer']) ? (string) $q['disclaimer'] : null,
            (bool) ($q['capture_user_data'] ?? false),
            (bool) ($q['landing_page'] ?? false),
            (string) ($q['type'] ?? 'default'),
            (bool) ($q['is_active'] ?? true),
            null === $onCompleted ? null : ($scoringVisible ? OnCompletedOutput::fromArray($onCompleted) : OnCompletedOutput::typeOnly($onCompleted)),
            (string) ($q['parent'] ?? 'ROOT'),
            isset($q['origin_session_id']) ? (string) $q['origin_session_id'] : null,
            isset($q['session_id']) ? (string) $q['session_id'] : null,
            isset($q['started_at']) ? (string) $q['started_at'] : null,
            isset($q['ended_at']) ? (string) $q['ended_at'] : null,
            (int) ($q['question_count'] ?? \count((array) ($q['questions'] ?? []))),
            (bool) ($q['is_chain'] ?? false),
            isset($q['slug']) ? (string) $q['slug'] : null,
            QuestionOutput::list((array) ($q['questions'] ?? [])),
            isset($q['created_at']) ? (string) $q['created_at'] : null,
            isset($q['updated_at']) ? (string) $q['updated_at'] : null,
        );
    }
}
