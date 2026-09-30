<?php

namespace App\Responses\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class QuestionNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('QUESTION_NOT_FOUND', 'The session has no such question.');
    }
}
