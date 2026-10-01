<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409: "send for correction" needs every answer of the attempt reviewed (PRD §7.11). */
final class ReviewIncomplete extends Conflict
{
    public function __construct()
    {
        parent::__construct('REVIEW_INCOMPLETE', 'Review every answer before sending it for correction.');
    }
}
