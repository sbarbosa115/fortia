<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * "Send for correction" (PRD §7.11 Retry, §8.8 POST /assignations/{id}/retries), steps 1–3: a new session (attempt
 * n+1) where the approved answers are copied and locked and the rejected ones cleared with their review kept; the
 * attempt is appended, shared_session_id moves to it and last_reminder_sent_at is cleared. Returns
 * {attempt, session_id, rejected}. The email (step 4) is NotifyRetry, sent after this commits.
 *
 * D3: the caller must own the assignation (another account's is 404).
 */
final class RetryFollowUp
{
    public function __construct(
        public readonly Caller $caller,
        public readonly string $assignationsId,
    ) {
    }
}
