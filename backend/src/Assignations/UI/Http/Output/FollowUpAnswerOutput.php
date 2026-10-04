<?php

namespace App\Assignations\UI\Http\Output;

use App\Shared\UI\Http\Output\Document\ReviewOutput;
use App\Shared\UI\Http\Output\Document\TableAnswerOutput;
use OpenApi\Attributes as OA;

/**
 * One answerable question of a follow-up attempt with its answer and review (the console's table, PRD §10.11).
 * review_state: `locked` (approved in an earlier attempt), `approved` / `rejected` in this attempt, or `not_reviewed`.
 */
final class FollowUpAnswerOutput
{
    public function __construct(
        public readonly string $question_id,
        /** 1-based among the answerable questions. */
        public readonly int $position,
        public readonly string $title,
        public readonly string $type,
        /** The answer as text (option labels, "7 / 10", file names); null when unanswered. */
        public readonly ?string $answer,
        /** A table question's answer as a table (its columns and rows); null for other questions or when unanswered. */
        public readonly ?TableAnswerOutput $answer_table,
        public readonly bool $skipped,
        public readonly ?string $answered_at,
        public readonly bool $locked,
        public readonly ?ReviewOutput $review,
        #[OA\Property(enum: ['locked', 'approved', 'rejected', 'not_reviewed'])]
        public readonly string $review_state,
    ) {
    }

    /** @param array<string, mixed> $a */
    public static function of(array $a): self
    {
        /** @var array<string, mixed>|null $review */
        $review = $a['review'];
        /** @var array{columns: list<array{key: string, label: string}>, rows: list<array{label: ?string, cells: array<string, string>}>}|null $table */
        $table = $a['answer_table'] ?? null;

        return new self(
            (string) $a['question_id'],
            (int) $a['position'],
            (string) $a['title'],
            (string) $a['type'],
            null === $a['answer'] ? null : (string) $a['answer'],
            null === $table ? null : TableAnswerOutput::fromArray($table),
            (bool) $a['skipped'],
            null === $a['answered_at'] ? null : (string) $a['answered_at'],
            (bool) $a['locked'],
            null === $review ? null : ReviewOutput::fromArray($review),
            (string) $a['review_state'],
        );
    }
}
