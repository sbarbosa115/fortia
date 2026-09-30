<?php

declare(strict_types=1);

namespace App\Questionnaires\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class QuestionnaireNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('QUESTIONNAIRE_NOT_FOUND', 'The questionnaire does not exist.');
    }
}
