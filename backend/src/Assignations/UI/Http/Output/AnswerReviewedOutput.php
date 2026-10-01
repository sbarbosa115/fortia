<?php

namespace App\Assignations\UI\Http\Output;

use App\Shared\UI\Http\Output\Document\ReviewOutput;
use OpenApi\Attributes as OA;

/** PUT /assignations/{id}/reviews/{question_id} (PRD §8.8): the decision recorded and the follow-up's review state. */
final class AnswerReviewedOutput
{
    public function __construct(
        public readonly string $question_id,
        public readonly ReviewOutput $review,
        #[OA\Property(enum: ['not_ready', 'in_review', 'changes_requested', 'approved'])]
        public readonly string $review_status,
    ) {
    }
}
