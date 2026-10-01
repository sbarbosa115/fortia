<?php

namespace App\Identity\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * PRD §8.2 POST /password-recovery/confirm. The password's length is the domain's rule (400 INVALID_PASSWORD), not
 * a shape check.
 */
final class PasswordRecoveryConfirmInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 64)]
    public ?string $code = null;

    #[Assert\NotNull]
    #[Assert\Length(max: 4096)]
    public ?string $password = null;
}
