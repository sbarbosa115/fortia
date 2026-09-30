<?php

namespace App\Identity\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class OnboardingInput
{
    #[Assert\NotNull]
    public ?bool $completed = null;
}
