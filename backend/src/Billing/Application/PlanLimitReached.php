<?php

namespace App\Billing\Application;

use App\Billing\Domain\PlanRejection;
use App\Shared\Domain\Error\TooManyRequests;

/** 429 PLAN_LIMIT_REACHED with details {reason, feature} (PRD §7.1, Appendix B). */
final class PlanLimitReached extends TooManyRequests
{
    public static function because(PlanRejection $rejection): self
    {
        return new self('PLAN_LIMIT_REACHED', self::message($rejection->reason), ['reason' => $rejection->reason, 'feature' => $rejection->feature]);
    }

    private static function message(string $reason): string
    {
        return match ($reason) {
            'NO_PLAN' => 'The account has no plan.',
            'PLAN_INACTIVE' => 'The plan is not active today.',
            'PLAN_NOT_FOUND' => 'The plan no longer exists.',
            'FEATURE_NOT_IN_PLAN' => 'The plan does not include this feature.',
            'FEATURE_LIMIT_REACHED' => 'The plan limit for this feature has been reached.',
            'RESPONSE_LIMIT_REACHED' => 'The response limit has been reached.',
            'QUESTIONNAIRE_LIMIT_REACHED' => 'The questionnaire limit has been reached.',
            default => 'The plan limit has been reached.',
        };
    }
}
