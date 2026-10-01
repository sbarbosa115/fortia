<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400: only follow-up assignations can belong to a project (PRD §7.12). */
final class AssignationNotFollowUp extends Rejected
{
    public function __construct(string $assignationsId)
    {
        parent::__construct('ASSIGNATION_NOT_FOLLOW_UP', 'Only follow-up assignations can belong to a project.', ['assignation_id' => $assignationsId]);
    }
}
