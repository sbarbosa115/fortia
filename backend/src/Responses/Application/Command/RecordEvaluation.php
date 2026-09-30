<?php

namespace App\Responses\Application\Command;

/**
 * The outcome of an answer's AI evaluation, on the stored session (PRD §7.9): when it did not pass, max_followups
 * goes down by one (never below 0) and the improvement message and the flagged answer are kept; when it passed, the
 * message is cleared. Returns the question as stored.
 */
final class RecordEvaluation
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $questionId,
        public readonly bool $passed,
        public readonly ?string $improvementMessage = null,
        public readonly mixed $flaggedAnswer = null,
    ) {
    }
}
