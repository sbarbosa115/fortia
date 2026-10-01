<?php

namespace App\Commerce\Application\Command;

/**
 * Ties products to the quiz funnel created from them (PRD §7.7: the recommendation looks up the questionnaire's
 * products first, then the account's).
 */
final class AttachCatalog
{
    /** @param list<string> $productIds */
    public function __construct(
        public readonly string $customerId,
        public readonly array $productIds,
        public readonly string $questionnaireId,
    ) {
    }
}
