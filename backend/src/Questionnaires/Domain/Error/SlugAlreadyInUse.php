<?php

namespace App\Questionnaires\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A flow's slug is unique across the whole system (PRD §6.6). */
final class SlugAlreadyInUse extends Conflict
{
    public function __construct()
    {
        parent::__construct('SLUG_ALREADY_IN_USE', 'That custom link (slug) is already in use by another questionnaire.');
    }
}
