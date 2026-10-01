<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * A reviewer's decision on one answer of a follow-up (PRD §7.11, §8.8 PUT /assignations/{id}/reviews/{question_id}):
 * `approved` or `rejected`, with an optional comment (≤ 1000; empty becomes null). Only for follow-ups (400
 * NOT_A_FOLLOW_UP), once complete (409 FOLLOW_UP_NOT_COMPLETED), on an answerable question of the current attempt
 * (404 QUESTION_NOT_FOUND; a message slide is not one) that is not locked (400 QUESTION_LOCKED).
 */
final class ReviewAnswer
{
    public function __construct(
        public readonly Caller $caller,
        public readonly string $assignationsId,
        public readonly string $questionId,
        public readonly string $status,
        public readonly ?string $comment,
    ) {
    }
}
