<?php

namespace App\Identity\UI\Http\Input;

use App\Identity\Domain\Model\Customer;
use App\Identity\Domain\Model\EmailAddress;
use Symfony\Component\Validator\Constraints as Assert;

/** PRD §8.2 POST /register. */
final class RegisterInput
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

    #[Assert\NotNull]
    #[Assert\Choice(choices: Customer::LANGUAGES)]
    public ?string $language = 'es-CO';

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public ?string $source = 'default';
}
