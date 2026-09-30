<?php

declare(strict_types=1);

namespace App\Billing\UI\Http\Output;

use App\Billing\Domain\PlanRejection;
use OpenApi\Attributes as OA;

/** Whether the plan lets the account use a feature now, and why not. */
final class FeatureVerdictOutput
{
    public function __construct(
        public readonly bool $allowed,
        #[OA\Property(enum: PlanRejection::REASONS, nullable: true)]
        public readonly ?string $reason,
        public readonly ?int $limit,
        public readonly int $used,
    ) {
    }
}
