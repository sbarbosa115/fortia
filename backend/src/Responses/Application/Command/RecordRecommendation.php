<?php

namespace App\Responses\Application\Command;

/**
 * Stores the products recommended for a quiz funnel session and completes it (PRD §7.7 "E-commerce / quiz
 * funnel", then step 3). Dispatched by the recommendation job.
 */
final class RecordRecommendation
{
    /** @param list<array<string, mixed>> $products the chosen products, best match first */
    public function __construct(
        public readonly string $sessionId,
        public readonly array $products,
    ) {
    }
}
