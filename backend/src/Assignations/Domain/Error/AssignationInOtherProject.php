<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409: an assignation belongs to at most one project (PRD §6.16, §7.12). */
final class AssignationInOtherProject extends Conflict
{
    public function __construct(string $assignationsId, string $projectId)
    {
        parent::__construct('ASSIGNATION_IN_OTHER_PROJECT', 'The assignation already belongs to another project.', ['assignation_id' => $assignationsId, 'project_id' => $projectId]);
    }
}
