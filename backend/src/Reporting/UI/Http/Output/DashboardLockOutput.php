<?php

namespace App\Reporting\UI\Http\Output;

use OpenApi\Attributes as OA;

/** Why a new dashboard is locked: the plan has no "dashboards" capacity (PRD §7.10); reason as in PLAN_LIMIT_REACHED. */
final class DashboardLockOutput
{
    public function __construct(
        #[OA\Property(enum: ['dashboards'])]
        public readonly string $feature,
        #[OA\Property(enum: ['NO_PLAN', 'PLAN_INACTIVE', 'PLAN_NOT_FOUND', 'FEATURE_NOT_IN_PLAN', 'FEATURE_LIMIT_REACHED', 'RESPONSE_LIMIT_REACHED', 'QUESTIONNAIRE_LIMIT_REACHED'])]
        public readonly string $reason,
    ) {
    }
}
