<?php

namespace App\Commerce\Application\Command;

/**
 * POST /questionnaire/quiz-funnel (PRD §8.4, §7.17): starts the quiz funnel job. $storeUrl null = the store connected
 * through the e-commerce platform; $products null = none sent (the job uses the stored ones of that store, else
 * scrapes it). Returns the job id.
 */
final class CreateQuizFunnel
{
    /** @param list<array<string, mixed>>|null $products */
    public function __construct(
        public readonly string $customerId,
        public readonly string $variant,
        public readonly ?string $storeUrl,
        public readonly ?array $products,
    ) {
    }
}
