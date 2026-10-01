<?php

namespace App\Responses\Application\Command;

/**
 * Starts a respondent's session on a questionnaire (PRD §8.4, §6.9) and returns its id. The public route and the
 * assignations' respondent login both use it; "is it public" is not checked here — the caller
 * does that, in its own order (§7.11).
 *
 * An assignation's session is bound to the assignation, the member (null for a follow-up's shared session), its type
 * ("follow_up" or null) and the attempt. A retry passes the previous attempt's session as $carryOverFrom: approved
 * answers are copied and locked, the rest cleared, reviews kept (§7.11 "Retry").
 */
final class StartSession
{
    public function __construct(
        public readonly string $questionnaireId,
        public readonly ?string $assignationsId = null,
        public readonly ?string $organizationUserId = null,
        public readonly ?string $assignationType = null,
        public readonly int $attempt = 1,
        public readonly ?string $carryOverFrom = null,
    ) {
    }
}
