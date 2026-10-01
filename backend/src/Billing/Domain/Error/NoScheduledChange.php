<?php

namespace App\Billing\Domain\Error;

use App\Shared\Domain\Error\Rejected;

final class NoScheduledChange extends Rejected
{
    public function __construct()
    {
        parent::__construct('NO_SCHEDULED_CHANGE', 'There is no scheduled plan change to revert.');
    }
}
