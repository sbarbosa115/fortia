<?php

namespace App\Integrations\Domain;

/**
 * D19 (PRD §15): a failed webhook delivery is retried with a growing backoff — 1 min, 5 min, 30 min, 2 h, 6 h — so
 * a receiver that is down for a few hours still gets the event. After MAX_ATTEMPTS the delivery is failed and stays
 * in the log.
 */
final class WebhookRetryPolicy
{
    /** Seconds to wait after the Nth failed attempt (index 0 = after the first). */
    private const BACKOFF = [60, 300, 1800, 7200, 21600];
    public const MAX_ATTEMPTS = 6;

    /** When to try again after $attemptsMade attempts have failed, or null when there are none left. */
    public static function nextAttemptAt(int $attemptsMade, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        if ($attemptsMade >= self::MAX_ATTEMPTS) {
            return null;
        }
        $delay = self::BACKOFF[min(\count(self::BACKOFF) - 1, max(0, $attemptsMade - 1))];

        return $now->modify('+'.$delay.' seconds');
    }
}
