<?php

namespace App\Shared\UI\Http\Output\Document;

use App\Shared\Domain\Document\QuestionnaireType;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * A respondent's session (PRD §6.9): the questionnaire copy with its values, plus the session fields. on_completed
 * is only {type} so the scoring configuration is never exposed (§8.4).
 */
final class SessionOutput
{
    /**
     * @param list<QuestionOutput>                                      $questions
     * @param array{name?: string, email?: string, phone?: string}|null $user_data
     */
    public function __construct(
        public readonly string $session_id,
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
        public readonly string $parent,
        public readonly ?string $slug,
        public readonly ?string $started_at,
        public readonly ?string $ended_at,
        public readonly ?string $flow_id,
        #[OA\Property(enum: ['filling', 'filled_out', 'processing', 'completed'])]
        public readonly string $status,
        #[OA\Property(type: 'object', nullable: true, properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'email', type: 'string'), new OA\Property(property: 'phone', type: 'string')])]
        public readonly ?array $user_data,
        public readonly ?string $assignations_id,
        public readonly ?string $organization_user_id,
        #[OA\Property(enum: ['follow_up'], nullable: true)]
        public readonly ?string $assignation_type,
        public readonly int $attempt,
        public readonly int $question_count,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: QuestionOutput::class)))]
        public readonly array $questions,
    ) {
    }

    /** @param array<string, mixed> $s the SessionView data */
    public static function fromArray(array $s): self
    {
        $questions = QuestionOutput::list((array) ($s['questions'] ?? []));

        return new self(
            (string) $s['session_id'],
            (string) $s['questionnaire_id'],
            (string) $s['customer_id'],
            (string) ($s['title'] ?? ''),
            isset($s['description']) ? (string) $s['description'] : null,
            isset($s['disclaimer']) ? (string) $s['disclaimer'] : null,
            (bool) ($s['capture_user_data'] ?? false),
            (bool) ($s['landing_page'] ?? false),
            (string) ($s['type'] ?? 'default'),
            (bool) ($s['is_active'] ?? true),
            OnCompletedOutput::typeOnly(\is_array($s['on_completed'] ?? null) ? $s['on_completed'] : null),
            (string) ($s['parent'] ?? 'ROOT'),
            isset($s['slug']) ? (string) $s['slug'] : null,
            isset($s['started_at']) ? (string) $s['started_at'] : null,
            isset($s['ended_at']) ? (string) $s['ended_at'] : null,
            isset($s['flow_id']) ? (string) $s['flow_id'] : null,
            (string) ($s['status'] ?? 'filling'),
            \is_array($s['user_data'] ?? null) ? $s['user_data'] : null,
            isset($s['assignations_id']) ? (string) $s['assignations_id'] : null,
            isset($s['organization_user_id']) ? (string) $s['organization_user_id'] : null,
            isset($s['assignation_type']) ? (string) $s['assignation_type'] : null,
            (int) ($s['attempt'] ?? 1),
            \count($questions),
            $questions,
        );
    }
}
