<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400: a project only holds assignations of its own organization (PRD §7.12). */
final class AssignationOrganizationMismatch extends Rejected
{
    public function __construct(string $assignationsId)
    {
        parent::__construct('ASSIGNATION_ORGANIZATION_MISMATCH', "The assignation belongs to another organization than the project's.", ['assignation_id' => $assignationsId]);
    }
}
