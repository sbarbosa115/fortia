<?php

declare(strict_types=1);

namespace App\Branding\Domain\Repository;

use App\Branding\Domain\Model\CustomerStyles;

interface CustomerStylesRepository
{
    public function find(string $customerId): ?CustomerStyles;

    public function add(CustomerStyles $styles): void;
}
