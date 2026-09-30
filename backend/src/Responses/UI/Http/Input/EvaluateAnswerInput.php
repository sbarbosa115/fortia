<?php

namespace App\Responses\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The question object with the answer to evaluate (PRD §8.4 POST …/answers/{question_id}/evaluate). Only its
 * controls' values are read; the criteria and follow-ups left come from the stored session.
 */
final class EvaluateAnswerInput
{
    /** @var array<int|string, mixed>|null the question's controls, with the answer in "value" */
    #[Assert\NotNull]
    public ?array $options = null;
}
