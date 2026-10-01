<?php

namespace App\Billing\Domain\Error;

use App\Shared\Domain\Error\Rejected;

final class SamePlan extends Rejected
{
    public function __construct()
    {
        parent::__construct('SAME_PLAN', 'The subscription is already on that plan and billing interval.');
    }
}
