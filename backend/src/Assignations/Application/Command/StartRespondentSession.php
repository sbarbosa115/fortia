<?php

namespace App\Assignations\Application\Command;

/**
 * The respondent login of an assignation (PRD §7.11, §8.8 POST /assignations/{id}/sessions). Returns
 * {token, session_id}: the respondent token binds the assignation, the member and the session.
 *
 * Order: 404 for an unknown or inactive assignation, the `assignations` feature on the owner's plan, 409
 * FOLLOW_UP_COMPLETED (a follow-up, before the lookup), 400 MISSING_IDENTIFIER, the member by phone and/or email
 * (403 USER_NOT_FOUND / NOT_IN_AUDIENCE), the `responses` capacity, then the session: a follow-up's shared one
 * (opened on the first login as attempt 1), or the member's own (the open one again, or a new attempt).
 */
final class StartRespondentSession
{
    public function __construct(
        public readonly string $assignationsId,
        public readonly ?string $email,
        public readonly ?string $phone,
    ) {
    }
}
