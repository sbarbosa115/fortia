<?php

declare(strict_types=1);

namespace App\Identity\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class RefreshInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public ?string $refresh_token = null;
}
