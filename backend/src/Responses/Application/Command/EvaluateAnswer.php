<?php

namespace App\Responses\Application\Command;

use App\Shared\Application\Security\RespondentClaims;

/**
 * Asks for the AI evaluation of an answer (PRD §7.9, §8.4 POST …/answers/{question_id}/evaluate): starts the
 * answer_evaluation job and returns its id. $question is the question object the respondent app sends, with the
 * answer being evaluated (it may not be saved yet).
 */
final class EvaluateAnswer
{
    /** @param array<string, mixed> $question */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $questionId,
        public readonly array $question,
        public readonly ?RespondentClaims $claims = null,
    ) {
    }
}
