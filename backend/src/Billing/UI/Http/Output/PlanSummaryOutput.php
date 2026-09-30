<?php

declare(strict_types=1);

namespace App\Billing\UI\Http\Output;

/** A plan without its feature list (GET /customer/usage). */
final class PlanSummaryOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $plan_name,
        public readonly string $plan_description,
        public readonly ?int $max_questionnaires,
        public readonly ?int $max_responses,
        public readonly ?int $price_amount,
        public readonly string $currency,
        public readonly ?int $yearly_price_amount,
        public readonly int $trial_days,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function of(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['plan_name'],
            (string) ($row['plan_description'] ?? ''),
            isset($row['max_questionnaires']) ? (int) $row['max_questionnaires'] : null,
            isset($row['max_responses']) ? (int) $row['max_responses'] : null,
            isset($row['price_amount']) ? (int) $row['price_amount'] : null,
            (string) ($row['currency'] ?? 'usd'),
            isset($row['yearly_price_amount']) ? (int) $row['yearly_price_amount'] : null,
            (int) ($row['trial_days'] ?? 0),
        );
    }
}
