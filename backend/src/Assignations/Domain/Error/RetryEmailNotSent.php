<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\UpstreamFailed;

/** 502: the retry email failed; the attempt already exists (PRD §7.11 Retry step 4). */
final class RetryEmailNotSent extends UpstreamFailed
{
    public function __construct()
    {
        parent::__construct('RETRY_EMAIL_NOT_SENT', 'The new attempt was created, but the email could not be sent.');
    }
}
