<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400: an assignation in a project keeps its organization (PRD §7.11 "Other rules"). */
final class AssignationInProject extends Rejected
{
    public function __construct()
    {
        parent::__construct('ASSIGNATION_IN_PROJECT', 'The organization cannot change while the assignation is in a project.');
    }
}
