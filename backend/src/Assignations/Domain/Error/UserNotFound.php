<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\NotAllowed;

/** 403: no member matches every identifier sent (PRD §7.11 step 4–5). */
final class UserNotFound extends NotAllowed
{
    public function __construct()
    {
        parent::__construct('USER_NOT_FOUND', 'No member of this organization matches those details.');
    }
}
