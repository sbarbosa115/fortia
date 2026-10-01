<?php

namespace App\Identity\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** PRD §8.2 POST /password-recovery. */
final class PasswordRecoveryInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public ?string $email = null;
}
