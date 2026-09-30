<?php

namespace App\Questionnaires\UI\Http\Output;

/** GET /questionnaire/find (bare, PRD §8.4): {questionnaire_url: {FRONTEND_URL}/f/{flowId}}. */
final class QuestionnaireUrlOutput
{
    public function __construct(public readonly string $questionnaire_url)
    {
    }
}
