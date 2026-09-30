<?php

namespace App\Questionnaires\UI\Http\Output;

/** POST /questionnaire (PRD §8.4): {questionnaire_id}. */
final class QuestionnaireIdOutput
{
    public function __construct(public readonly string $questionnaire_id)
    {
    }
}
