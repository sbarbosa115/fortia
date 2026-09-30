<?php

namespace App\Branding\Infrastructure\Persistence;

use App\Branding\Domain\Model\CustomerStyles;
use App\Branding\Domain\Repository\CustomerStylesRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<CustomerStyles> */
final class DoctrineCustomerStylesRepository extends DoctrineRepository implements CustomerStylesRepository
{
    protected function entityClass(): string
    {
        return CustomerStyles::class;
    }

    public function find(string $customerId): ?CustomerStyles
    {
        return $this->findEntity($customerId);
    }

    public function add(CustomerStyles $styles): void
    {
        $this->persist($styles);
    }
}
