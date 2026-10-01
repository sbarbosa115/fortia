<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** 422: no member of the audience has an email (PRD §7.13 manual send). */
final class NoRecipients extends InvalidValue
{
    public function __construct()
    {
        parent::__construct('NO_RECIPIENTS', 'There is nobody to send it to.');
    }
}
