<?php

namespace App\Identity\UI\Http\Output;

/** PRD §8.2 GET /profile. */
final class ProfileOutput
{
    public function __construct(public readonly ProfileCustomerOutput $customer)
    {
    }
}
