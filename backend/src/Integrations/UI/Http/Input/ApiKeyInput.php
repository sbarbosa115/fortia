<?php

namespace App\Integrations\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /api-keys (PRD §8.11): name 1–100, expiration_days 1–3650 or absent/null (never expires). */
final class ApiKeyInput
{
    #[Assert\NotNull(message: 'Name is required.')]
    #[Assert\NotBlank(message: 'Name is required.')]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\Range(min: 1, max: 3650)]
    public ?int $expiration_days = null;
}
