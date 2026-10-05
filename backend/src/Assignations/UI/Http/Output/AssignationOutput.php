<?php

namespace App\Assignations\UI\Http\Output;

use App\Shared\UI\Http\Output\Document\QuestionOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * An enriched assignation (PRD §6.14, §8.8): its fields, organization_name, questionnaire_name, questionnaire_url
 * ({FRONTEND_URL}/a/{id}), audience_size, the current attempt, progress (§7.11), completed, review_status (null for a
 * default assignation) and its attempts. The respondent page (anonymous) never receives the description nor the
 * attempts' answers.
 */
final class AssignationOutput
{
    /**
     * @param list<QuestionOutput>           $questions
     * @param list<AssignationAttemptOutput> $attempts
     */
    public function __construct(
        public readonly string $assignations_id,
        public readonly string $customer_id,
        public readonly string $organization_id,
        public readonly string $organization_name,
        public readonly string $questionnaire_id,
        public readonly string $questionnaire_name,
        public readonly string $questionnaire_url,
        public readonly string $name,
        /** Internal note; never sent to the respondent page. */
        public readonly ?string $description,
        public readonly int $max_follow_ups,
        public readonly bool $active,
        #[OA\Property(enum: ['default', 'follow_up'])]
        public readonly string $type,
        public readonly ?string $due_date,
        public readonly AudienceOutput $audience,
        public readonly int $audience_size,
        /** The registration slide (the respondent login). */
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: QuestionOutput::class)))]
        public readonly array $questions,
        public readonly ?string $project_id,
        public readonly ?string $shared_session_id,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: AssignationAttemptOutput::class)))]
        public readonly array $attempts,
        /** The current attempt (1 before anybody opens a follow-up). */
        public readonly int $attempt,
        public readonly ?string $last_reminder_sent_at,
        public readonly AssignationProgressOutput $progress,
        public readonly bool $completed,
        #[OA\Property(enum: ['not_ready', 'in_review', 'changes_requested', 'approved', 'completed'], nullable: true)]
        public readonly ?string $review_status,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
        /** A complete follow-up goes to review; false: it is simply completed. */
        public readonly bool $requires_review = true,
    ) {
    }

    /** @param array<string, mixed> $d AssignationDetails' assignation */
    public static function of(array $d): self
    {
        /** @var list<array<string, mixed>> $questions */
        $questions = $d['questions'];
        /** @var list<array<string, mixed>> $attempts */
        $attempts = $d['attempts'];
        /** @var array{type: string, values: list<string>} $audience */
        $audience = $d['audience'];
        /** @var array<string, mixed> $progress */
        $progress = $d['progress'];

        return new self(
            (string) $d['assignations_id'],
            (string) $d['customer_id'],
            (string) $d['organization_id'],
            (string) $d['organization_name'],
            (string) $d['questionnaire_id'],
            (string) $d['questionnaire_name'],
            (string) $d['questionnaire_url'],
            (string) $d['name'],
            self::string($d['description']),
            (int) $d['max_follow_ups'],
            (bool) $d['active'],
            (string) $d['type'],
            self::string($d['due_date']),
            new AudienceOutput($audience['type'], $audience['values']),
            (int) $d['audience_size'],
            QuestionOutput::list($questions),
            self::string($d['project_id']),
            self::string($d['shared_session_id']),
            array_map(AssignationAttemptOutput::of(...), $attempts),
            (int) $d['attempt'],
            self::string($d['last_reminder_sent_at']),
            AssignationProgressOutput::of($progress),
            (bool) $d['completed'],
            self::string($d['review_status']),
            self::string($d['created_at']),
            self::string($d['updated_at']),
            (bool) ($d['requires_review'] ?? true),
        );
    }

    private static function string(mixed $value): ?string
    {
        return null === $value ? null : (string) $value;
    }
}
