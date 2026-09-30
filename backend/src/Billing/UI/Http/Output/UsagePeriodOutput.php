<?php

namespace App\Billing\UI\Http\Output;

final class UsagePeriodOutput
{
    public function __construct(
        public readonly int $questionnaires_used,
        public readonly string $from_at,
        public readonly string $to_at,
    ) {
    }
}
