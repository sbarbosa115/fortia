<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Error\CustomerNotFound;
use App\Identity\Domain\Model\Customer;
use App\Identity\Domain\Repository\CustomerRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Customer> */
final class DoctrineCustomerRepository extends DoctrineRepository implements CustomerRepository
{
    protected function entityClass(): string
    {
        return Customer::class;
    }

    public function find(string $customerId): ?Customer
    {
        return $this->findEntity($customerId);
    }

    public function get(string $customerId): Customer
    {
        return $this->find($customerId) ?? throw new CustomerNotFound();
    }

    public function add(Customer $customer): void
    {
        $this->persist($customer);
    }
}
