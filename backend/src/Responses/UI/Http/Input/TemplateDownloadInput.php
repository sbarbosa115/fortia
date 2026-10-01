<?php

namespace App\Responses\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /templates/download-urls: the key of a file question's template. */
final class TemplateDownloadInput
{
    #[Assert\NotNull]
    #[Assert\Length(min: 1, max: 255)]
    public ?string $key = null;
}
