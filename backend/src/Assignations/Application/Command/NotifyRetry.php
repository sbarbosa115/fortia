<?php

namespace App\Assignations\Application\Command;

/**
 * The email of "send for correction" (PRD §7.11 Retry step 4) to the audience: attempt $attempt is open. Returns how
 * many recipients it went to; 502 RETRY_EMAIL_NOT_SENT when it fails (the attempt already exists).
 */
final class NotifyRetry
{
    public function __construct(
        public readonly string $assignationsId,
        public readonly int $attempt,
        public readonly int $rejected,
    ) {
    }
}
