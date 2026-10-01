<?php

namespace App\Assignations\UI\Http\Output;

use App\Shared\UI\Http\Output\Document\FlowOutput;
use App\Shared\UI\Http\Output\Document\SessionOutput;

/**
 * POST /assignations/{id}/sessions (bare, PRD §8.8): the respondent token (send it as "Authorization: Bearer" on the
 * session and results calls), the session to answer and the questionnaire's flow, if it has one.
 */
final class RespondentLoginOutput
{
    public function __construct(
        public readonly string $token,
        public readonly SessionOutput $questionnaire,
        public readonly ?FlowOutput $flow,
    ) {
    }
}
