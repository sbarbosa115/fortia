<?php

namespace App\Responses\Application\Command;

use App\Shared\Application\Security\RespondentClaims;

/**
 * Submits a session (PRD §8.4 POST /questionnaire/session) and runs §7.7: saves the last values, sets ended_at,
 * computes the result by type and completes it — or, for a quiz funnel, starts the job that recommends products.
 * Returns a SubmitOutcome. Submitting a finished session again returns the same outcome and counts nothing.
 */
final class SubmitSession
{
    /**
     * @param array<int|string, mixed>                                  $questions
     * @param array{name?: string, email?: string, phone?: string}|null $userData
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly array $questions,
        public readonly ?array $userData = null,
        public readonly ?RespondentClaims $claims = null,
    ) {
    }
}
