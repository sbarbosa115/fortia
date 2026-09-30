<?php

namespace App\Questionnaires\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** PUT /questionnaire (PRD §8.4): the flow, plus the questionnaire it edits. */
final class UpdateFlowInput extends FlowInput
{
    #[Assert\NotBlank]
    public ?string $questionnaire_id = null;
}
