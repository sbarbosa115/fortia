<?php

namespace App\Responses\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class SessionNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('SESSION_NOT_FOUND', 'The session does not exist.');
    }
}
