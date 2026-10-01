<?php

namespace App\Assignations\UI\Http\Output;

use OpenApi\Attributes as OA;

/** An assignation within a project (PRD §7.12): its state, progress, reviews and due date. */
final class ProjectAssignationOutput
{
    public function __construct(
        public readonly string $assignations_id,
        public readonly string $name,
        public readonly string $questionnaire_id,
        public readonly bool $active,
        #[OA\Property(enum: ['review', 'overdue', 'correction', 'progress', 'pending', 'approved'])]
        public readonly string $state,
        /** The shared session has ended. */
        public readonly bool $completed,
        #[OA\Property(enum: ['not_ready', 'in_review', 'changes_requested', 'approved'])]
        public readonly string $review_status,
        public readonly int $attempt,
        /** Its own due date, or the project's. */
        public readonly ?string $due_date,
        public readonly bool $overdue,
        /** 0–100; a complete follow-up is 100. */
        public readonly int $percent,
        public readonly ProjectAssignationProgressOutput $progress,
        public readonly ProjectReviewCountsOutput $review,
    ) {
    }

    /** @param array<string, mixed> $data ProjectQueries' assignation row */
    public static function of(array $data): self
    {
        return new self(
            (string) $data['assignations_id'],
            (string) $data['name'],
            (string) $data['questionnaire_id'],
            (bool) $data['active'],
            (string) $data['state'],
            (bool) $data['completed'],
            (string) $data['review_status'],
            (int) $data['attempt'],
            null === $data['due_date'] ? null : (string) $data['due_date'],
            (bool) $data['overdue'],
            (int) $data['percent'],
            ProjectAssignationProgressOutput::of((array) $data['progress']),
            ProjectReviewCountsOutput::of((array) $data['review']),
        );
    }
}
