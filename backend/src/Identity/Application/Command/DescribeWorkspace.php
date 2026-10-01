<?php

namespace App\Identity\Application\Command;

/**
 * Onboarding step 2 (D15, PATCH /customer/workspace): the workspace name, account language and website. Only the
 * fields sent change. Returns ['name', 'language', 'website'].
 */
final class DescribeWorkspace
{
    /** @param array{name?: string|null, language?: string, website?: string|null} $fields */
    public function __construct(
        public readonly string $customerId,
        public readonly array $fields,
    ) {
    }
}
