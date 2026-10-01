<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400: every answer was approved: there is nothing to send back (PRD §7.11). */
final class NothingToRetry extends Rejected
{
    public function __construct()
    {
        parent::__construct('NOTHING_TO_RETRY', 'No answer was rejected, so there is nothing to correct.');
    }
}
