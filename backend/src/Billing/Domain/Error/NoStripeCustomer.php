<?php

namespace App\Billing\Domain\Error;

use App\Shared\Domain\Error\Rejected;

final class NoStripeCustomer extends Rejected
{
    public function __construct()
    {
        parent::__construct('NO_STRIPE_CUSTOMER', 'The account has no billing account in the payment gateway yet.');
    }
}
