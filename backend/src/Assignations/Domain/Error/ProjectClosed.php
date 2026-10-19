<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409: a complete or overdue project takes no more questionnaires. */
final class ProjectClosed extends Conflict
{
    public function __construct()
    {
        parent::__construct('PROJECT_CLOSED', 'This assignation is complete or overdue: it takes no more questionnaires.');
    }
}
