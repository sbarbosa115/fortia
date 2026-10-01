<?php

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** Console emails are unique across the whole system (PRD §4.3): sign-up or user creation with an email in use. */
final class EmailAlreadyExists extends Conflict
{
    public function __construct()
    {
        parent::__construct('EMAIL_ALREADY_EXISTS', 'An account with this email already exists.');
    }
}
