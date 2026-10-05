<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Model\Assignation;

/**
 * Review is chosen per follow-up; a project's own flag only sums it up: true when any of its follow-ups goes to review
 * once complete ($fallback when it has none).
 */
final class ProjectReview
{
    /** @param list<Assignation> $assignations */
    public static function any(array $assignations, bool $fallback): bool
    {
        if ([] === $assignations) {
            return $fallback;
        }
        foreach ($assignations as $assignation) {
            if ($assignation->requiresReview()) {
                return true;
            }
        }

        return false;
    }
}
