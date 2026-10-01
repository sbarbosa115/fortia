<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\NotAllowed;

/** 403: the member exists but is not in the assignation's audience (PRD §7.11 step 5). */
final class NotInAudience extends NotAllowed
{
    public function __construct()
    {
        parent::__construct('NOT_IN_AUDIENCE', 'This assignation is not addressed to you.');
    }
}
