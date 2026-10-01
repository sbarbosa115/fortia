<?php

namespace App\Billing\Application\Command;

/**
 * The free trial of every new account (PRD §7.3): the "starter" plan from today until today + 1 calendar month (the
 * day clamped to the month's length). Dispatched by Identity on sign-up and on a first Google login.
 */
final class AssignStarterPlan
{
    public const PLAN_ID = 'starter';

    public function __construct(public readonly string $customerId)
    {
    }
}
