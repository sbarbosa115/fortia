<?php

namespace App\Billing\Domain\Repository;

use App\Billing\Domain\Model\Payment;

interface PaymentRepository
{
    public function add(Payment $payment): void;

    /** @return list<Payment> newest first */
    public function ofCustomer(string $customerId): array;
}
