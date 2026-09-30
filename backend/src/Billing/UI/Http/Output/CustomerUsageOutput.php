<?php

declare(strict_types=1);

namespace App\Billing\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /customer/usage (PRD §8.3): the plan, the period's usage and one verdict per feature of the catalog. */
final class CustomerUsageOutput
{
    /**
     * @param array<string, FeatureVerdictOutput> $features
     */
    public function __construct(
        public readonly ?CustomerPlanOutput $customer_plan,
        public readonly ?PlanSummaryOutput $plan,
        public readonly ?UsagePeriodOutput $usage,
        public readonly bool $plan_active,
        #[OA\Property(type: 'object', additionalProperties: new OA\AdditionalProperties(ref: new Model(type: FeatureVerdictOutput::class)))]
        public readonly array $features,
    ) {
    }

    /**
     * @param array{
     *     customer_plan: array<string, mixed>|null,
     *     plan: array<string, mixed>|null,
     *     usage: array{questionnaires_used: int, from_at: string, to_at: string, features: array<string, int>}|null,
     *     plan_active: bool,
     *     features: array<string, array{allowed: bool, reason: string|null, limit: int|null, used: int}>,
     * } $data
     */
    public static function of(array $data): self
    {
        $features = [];
        foreach ($data['features'] as $slug => $verdict) {
            $features[$slug] = new FeatureVerdictOutput($verdict['allowed'], $verdict['reason'], $verdict['limit'], $verdict['used']);
        }

        return new self(
            null === $data['customer_plan'] ? null : CustomerPlanOutput::of($data['customer_plan']),
            null === $data['plan'] ? null : PlanSummaryOutput::of($data['plan']),
            null === $data['usage'] ? null : new UsagePeriodOutput($data['usage']['questionnaires_used'], $data['usage']['from_at'], $data['usage']['to_at']),
            $data['plan_active'],
            $features,
        );
    }
}
