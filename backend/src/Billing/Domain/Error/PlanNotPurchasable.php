<?php

namespace App\Billing\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** The plan has no gateway price for that billing interval ("price on request"). */
final class PlanNotPurchasable extends Rejected
{
    public function __construct()
    {
        parent::__construct('PLAN_NOT_PURCHASABLE', 'That plan cannot be bought online with that billing interval.');
    }
}
