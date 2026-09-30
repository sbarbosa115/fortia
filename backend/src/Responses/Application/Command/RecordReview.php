<?php

namespace App\Responses\Application\Command;

/**
 * A reviewer's decision on one answer of a follow-up (PRD §6.9 review, §7.11), written on the session:
 * {status: approved | rejected, comment?, reviewed_at, attempt}. The assignation rules around it (only follow-ups,
 * only once complete, not on message slides) are checked by the caller; a locked answer is refused here
 * (400 QUESTION_LOCKED) and an unknown question is 404 QUESTION_NOT_FOUND.
 */
final class RecordReview
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $questionId,
        public readonly string $status,
        public readonly ?string $comment,
        public readonly int $attempt,
    ) {
    }
}
