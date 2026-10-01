<?php

namespace App\Assignations\Domain\Error;

use App\Assignations\Domain\ProjectStatus;
use App\Shared\Domain\Error\Rejected;

/** 400: the `status` filter of GET /projects is not one of review, progress, correction, overdue, approved. */
final class InvalidProjectStatus extends Rejected
{
    public function __construct()
    {
        parent::__construct('INVALID_PROJECT_STATUS', 'status must be one of: '.implode(', ', ProjectStatus::FILTERS).'.');
    }
}
