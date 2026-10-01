<?php

namespace App\Assignations\Domain;

use App\Assignations\Domain\Error\FollowUpNotCompleted;
use App\Assignations\Domain\Error\NothingToRetry;
use App\Assignations\Domain\Error\ReviewIncomplete;

/**
 * When a follow-up's answers can be reviewed and sent for correction (PRD §7.11):
 *
 * - review: only once it is complete (409 FOLLOW_UP_NOT_COMPLETED);
 * - retry ("send for correction"): only once it is complete, every answer of the attempt is reviewed (409
 *   REVIEW_INCOMPLETE) and some answer was rejected (400 NOTHING_TO_RETRY when every one is approved).
 */
final class FollowUpRules
{
    public static function assertReviewable(FollowUpProgress $progress): void
    {
        if (!$progress->ended) {
            throw new FollowUpNotCompleted();
        }
    }

    public static function assertRetryable(FollowUpProgress $progress): void
    {
        self::assertReviewable($progress);
        match ($progress->reviewStatus) {
            FollowUpProgress::CHANGES_REQUESTED => null,
            FollowUpProgress::APPROVED => throw new NothingToRetry(),
            default => throw new ReviewIncomplete(),
        };
    }
}
