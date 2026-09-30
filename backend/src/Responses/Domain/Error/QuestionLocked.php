<?php

namespace App\Responses\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** PRD §7.11: a locked (approved in an earlier attempt) answer is not reviewed again. */
final class QuestionLocked extends Rejected
{
    public function __construct()
    {
        parent::__construct('QUESTION_LOCKED', 'The answer was approved in an earlier attempt and cannot change.');
    }
}
