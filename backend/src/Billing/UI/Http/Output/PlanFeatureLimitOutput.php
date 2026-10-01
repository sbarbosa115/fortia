<?php

namespace App\Billing\UI\Http\Output;

/** One feature of a plan and its limit: < 0 unlimited, 0 not included (PRD §6.4). */
final class PlanFeatureLimitOutput
{
    public function __construct(
        public readonly string $feature_id,
        public readonly string $feature_name,
        public readonly int $limit,
    ) {
    }
}
