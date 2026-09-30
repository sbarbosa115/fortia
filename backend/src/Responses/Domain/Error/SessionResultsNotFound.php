<?php

namespace App\Responses\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class SessionResultsNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('SESSION_RESULTS_NOT_FOUND', 'The session has no results.');
    }
}
