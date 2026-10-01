<?php

namespace App\Billing\Domain\Error;

use App\Shared\Domain\Error\UpstreamFailed;

/** 502 EMAIL_UNAVAILABLE: the sales lead could not be sent (PRD §8.3 POST /contact). */
final class EmailUnavailable extends UpstreamFailed
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('EMAIL_UNAVAILABLE', 'The email could not be sent.', [], $previous);
    }
}
