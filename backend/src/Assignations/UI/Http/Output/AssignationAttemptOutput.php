<?php

namespace App\Assignations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * One attempt of a follow-up (PRD §6.14 attempts, §7.11): its shared session and that session's state. `answers` is
 * the attempt's answers with their review, only for the console owner in GET /assignations/{id}.
 */
final class AssignationAttemptOutput
{
    /** @param list<FollowUpAnswerOutput>|null $answers */
    public function __construct(
        public readonly int $number,
        public readonly string $session_id,
        public readonly string $created_at,
        public readonly ?string $status,
        public readonly ?string $started_at,
        public readonly ?string $ended_at,
        public readonly bool $completed,
        #[OA\Property(enum: ['not_ready', 'in_review', 'changes_requested', 'approved'])]
        public readonly string $review_status,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(ref: new Model(type: FollowUpAnswerOutput::class)))]
        public readonly ?array $answers,
    ) {
    }

    /** @param array<string, mixed> $a */
    public static function of(array $a): self
    {
        /** @var list<array<string, mixed>>|null $answers */
        $answers = $a['answers'] ?? null;

        return new self(
            (int) $a['number'],
            (string) $a['session_id'],
            (string) $a['created_at'],
            isset($a['status']) ? (string) $a['status'] : null,
            isset($a['started_at']) ? (string) $a['started_at'] : null,
            isset($a['ended_at']) ? (string) $a['ended_at'] : null,
            (bool) ($a['completed'] ?? false),
            (string) ($a['review_status'] ?? 'not_ready'),
            null === $answers ? null : array_map(FollowUpAnswerOutput::of(...), $answers),
        );
    }
}
