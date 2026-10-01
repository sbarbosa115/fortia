<?php

namespace App\Billing\Domain\Error;

use App\Shared\Domain\Error\Rejected;

final class NoSubscription extends Rejected
{
    public function __construct()
    {
        parent::__construct('NO_SUBSCRIPTION', 'The account has no subscription.');
    }
}
