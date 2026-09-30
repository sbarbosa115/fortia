<?php

namespace App\Questionnaires\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A questionnaire with any response (a value or a skip in any session) cannot be edited (PRD §7.5). */
final class QuestionnaireAlreadyAnswered extends Conflict
{
    public function __construct()
    {
        parent::__construct('QUESTIONNAIRE_ALREADY_ANSWERED', 'The questionnaire already has answers, so it cannot be edited. Create a copy instead.');
    }
}
