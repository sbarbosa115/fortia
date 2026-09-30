<?php

namespace App\Billing\Domain;

/** Why the plan gate said no: one of the seven reasons of PRD Appendix B (PLAN_LIMIT_REACHED details.reason). */
final class PlanRejection
{
    public const REASONS = [
        'NO_PLAN', 'PLAN_INACTIVE', 'PLAN_NOT_FOUND', 'FEATURE_NOT_IN_PLAN', 'FEATURE_LIMIT_REACHED',
        'RESPONSE_LIMIT_REACHED', 'QUESTIONNAIRE_LIMIT_REACHED',
    ];

    public function __construct(
        public readonly string $reason,
        public readonly string $feature,
        public readonly ?int $limit = null,
    ) {
    }
}
