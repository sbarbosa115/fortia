<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\Customer;

interface CustomerRepository
{
    public function find(string $customerId): ?Customer;

    /** @throws \App\Identity\Domain\Error\CustomerNotFound */
    public function get(string $customerId): Customer;

    public function add(Customer $customer): void;
}
