<?php

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** Only Customer-Admin and Customer-Read-Only can be assigned (PRD §4.2 ASSIGNABLE_ROLES). */
final class InvalidRole extends Rejected
{
    public function __construct()
    {
        parent::__construct('INVALID_ROLE', 'The role must be Customer-Admin or Customer-Read-Only.');
    }
}
