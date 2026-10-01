<?php

namespace App\Billing\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** A plan as GET /plans shows it to an account (PRD §8.3). */
final class CatalogPlanOutput
{
    /**
     * @param list<PlanFeatureLimitOutput> $features
     */
    public function __construct(
        public readonly string $id,
        public readonly string $plan_name,
        public readonly string $plan_description,
        public readonly ?int $price_amount,
        public readonly string $currency,
        public readonly bool $purchasable,
        public readonly ?int $yearly_price_amount,
        public readonly bool $yearly_purchasable,
        public readonly ?int $max_questionnaires,
        public readonly ?int $max_responses,
        public readonly int $trial_days,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: PlanFeatureLimitOutput::class)))]
        public readonly array $features,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function of(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['plan_name'],
            (string) $row['plan_description'],
            null === $row['price_amount'] ? null : (int) $row['price_amount'],
            (string) $row['currency'],
            (bool) $row['purchasable'],
            null === $row['yearly_price_amount'] ? null : (int) $row['yearly_price_amount'],
            (bool) $row['yearly_purchasable'],
            null === $row['max_questionnaires'] ? null : (int) $row['max_questionnaires'],
            null === $row['max_responses'] ? null : (int) $row['max_responses'],
            (int) $row['trial_days'],
            array_map(
                static fn (array $f): PlanFeatureLimitOutput => new PlanFeatureLimitOutput((string) $f['feature_id'], (string) $f['feature_name'], (int) $f['limit']),
                (array) $row['features'],
            ),
        );
    }
}
