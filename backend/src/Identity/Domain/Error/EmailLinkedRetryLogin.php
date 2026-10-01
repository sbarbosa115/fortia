<?php

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/**
 * The Google account was just linked to an existing password account with the same email: the console retries the
 * sign-in once (PRD §10.2, §13.1).
 */
final class EmailLinkedRetryLogin extends Conflict
{
    public function __construct()
    {
        parent::__construct('EMAIL_LINKED_RETRY_LOGIN', 'Your Google account was linked to your existing account. Sign in again.');
    }
}
