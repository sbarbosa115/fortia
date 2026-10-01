<?php

namespace App\Assignations\UI\Http\Output;

/** POST /assignations/{id}/retries (PRD §8.8): the new attempt, its session and how many were emailed. */
final class RetryOutput
{
    public function __construct(
        public readonly int $attempt,
        public readonly string $session_id,
        public readonly int $recipients,
    ) {
    }
}
