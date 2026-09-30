<?php

declare(strict_types=1);

namespace App\Identity\UI\Http\Output;

final class OnboardingOutput
{
    public function __construct(public readonly bool $onboarding_completed)
    {
    }
}
