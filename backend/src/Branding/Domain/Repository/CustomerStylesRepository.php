<?php

namespace App\Branding\Domain\Repository;

use App\Branding\Domain\Model\CustomerStyles;

interface CustomerStylesRepository
{
    public function find(string $customerId): ?CustomerStyles;

    public function add(CustomerStyles $styles): void;
}
