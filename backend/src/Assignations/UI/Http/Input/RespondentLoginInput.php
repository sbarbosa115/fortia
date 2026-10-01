<?php

namespace App\Assignations\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /assignations/{id}/sessions (PRD §8.8): the registration slide's fields. No extra fields. The member is looked
 * up only by email and/or phone (§7.11); name, role and area are not used to find them.
 */
final class RespondentLoginInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 200)]
    public ?string $name = null;

    #[Assert\Length(max: 254)]
    public ?string $email = null;

    #[Assert\Length(max: 50)]
    public ?string $phone = null;

    #[Assert\Length(max: 120)]
    public ?string $role = null;

    #[Assert\Length(max: 120)]
    public ?string $area = null;
}
