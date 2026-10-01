<?php

namespace App\Identity\UI\Http\Input;

use App\Identity\Domain\Model\EmailAddress;
use Symfony\Component\Validator\Constraints as Assert;

/** PRD §8.2 POST /users. The role is checked by the use case (400 INVALID_ROLE). */
final class CreateUserInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[Assert\Regex(pattern: EmailAddress::PATTERN, message: 'Enter a valid email address.', normalizer: 'trim')]
    public ?string $email = null;

    #[Assert\NotNull]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Password must be at least 8 characters.')]
    public ?string $password = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 50)]
    public ?string $name = null;

    #[Assert\NotBlank]
    public ?string $role = null;
}
