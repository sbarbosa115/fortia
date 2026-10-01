<?php

namespace App\Identity\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** The return from Google's consent screen: ?code, and the PKCE verifier the console kept. */
final class GoogleTokenInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 2048)]
    public ?string $code = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 256)]
    public ?string $code_verifier = null;
}
