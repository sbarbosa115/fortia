<?php

namespace App\Identity\Application\Command;

/**
 * Whether the account finished onboarding (PRD §7.15). A legacy account (null) gets it derived from "does it have any
 * questionnaire?" and stored; a caller without an account row gets true. Returns a bool.
 */
final class ResolveOnboarding
{
    public function __construct(public readonly string $customerId)
    {
    }
}
