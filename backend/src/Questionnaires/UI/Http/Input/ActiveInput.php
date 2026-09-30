<?php

namespace App\Questionnaires\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** PATCH /questionnaire/{id}: exactly {is_active: strict bool} (PRD §8.4). */
final class ActiveInput
{
    #[Assert\NotNull]
    public ?bool $is_active = null;
}
