<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

/** The console sets the onboarding flag explicitly (PRD §7.15). 404 CUSTOMER_NOT_FOUND without an account row. */
final class SetOnboarding
{
    public function __construct(
        public readonly string $customerId,
        public readonly bool $completed,
    ) {
    }
}
