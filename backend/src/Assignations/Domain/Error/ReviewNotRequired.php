<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409: the follow-up's assignation does not require review, so its answers are not reviewed nor sent for correction. */
final class ReviewNotRequired extends Conflict
{
    public function __construct()
    {
        parent::__construct('REVIEW_NOT_REQUIRED', 'This assignation does not require review.');
    }
}
