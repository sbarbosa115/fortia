<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400: the respondent login needs an email or a phone (PRD §7.11 step 3). */
final class MissingIdentifier extends Rejected
{
    public function __construct()
    {
        parent::__construct('MISSING_IDENTIFIER', 'Send an email or a phone to sign in.');
    }
}
