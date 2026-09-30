<?php

namespace App\Responses\Application\Command;

use App\Shared\Application\Security\RespondentClaims;

/**
 * Saves a respondent's progress (PRD §8.4 PUT /questionnaire/session): the values of the questions they sent. In a
 * follow-up's shared session the answers merge (§7.11) and, once it ended, saving is 409 FOLLOW_UP_COMPLETED. An
 * assignation's session needs its respondent token ($claims).
 */
final class SaveSession
{
    /** @param array<int|string, mixed> $questions the questions as the respondent app sends them */
    public function __construct(
        public readonly string $sessionId,
        public readonly array $questions,
        public readonly ?RespondentClaims $claims = null,
    ) {
    }
}
