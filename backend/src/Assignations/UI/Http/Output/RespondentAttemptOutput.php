<?php

namespace App\Assignations\UI\Http\Output;

/** One session a member started for an assignation (their attempt history, PRD §10.11). */
final class RespondentAttemptOutput
{
    public function __construct(
        public readonly int $number,
        public readonly string $session_id,
        public readonly string $status,
        public readonly ?string $started_at,
        public readonly ?string $ended_at,
    ) {
    }
}
