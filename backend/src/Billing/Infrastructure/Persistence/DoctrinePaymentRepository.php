<?php

namespace App\Billing\Infrastructure\Persistence;

use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\Repository\PaymentRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Payment> */
final class DoctrinePaymentRepository extends DoctrineRepository implements PaymentRepository
{
    protected function entityClass(): string
    {
        return Payment::class;
    }

    public function add(Payment $payment): void
    {
        $this->persist($payment);
    }

    public function ofCustomer(string $customerId): array
    {
        return $this->repository()->findBy(['customerId' => $customerId], ['paidAt' => 'DESC', 'id' => 'DESC']);
    }
}
