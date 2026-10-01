<?php

namespace App\Billing\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class PlanNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('PLAN_NOT_FOUND', 'That plan does not exist.');
    }
}
