<?php

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\Unavailable;

/** Google sign-in without a Google OAuth client configured (PRD §10.2 "Sign-in is temporarily unavailable…"). */
final class ProviderNotConfigured extends Unavailable
{
    public function __construct()
    {
        parent::__construct('PROVIDER_NOT_CONFIGURED', 'Sign-in with Google is not available right now.');
    }
}
