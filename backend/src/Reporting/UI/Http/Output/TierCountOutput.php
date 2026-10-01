<?php

namespace App\Reporting\UI\Http\Output;

/** How many sessions scored into a diagnostic tier. */
final class TierCountOutput
{
    public function __construct(
        public readonly string $tier_id,
        public readonly string $name,
        public readonly int $count,
    ) {
    }
}
