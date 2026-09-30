<?php

declare(strict_types=1);

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class CustomerNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('CUSTOMER_NOT_FOUND', 'The account does not exist.');
    }
}
