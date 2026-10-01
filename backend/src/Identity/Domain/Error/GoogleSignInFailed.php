<?php

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\Unauthenticated;

/** Google did not accept the authorization code (expired, reused, or a wrong verifier). */
final class GoogleSignInFailed extends Unauthenticated
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('GOOGLE_SIGN_IN_FAILED', 'Google did not confirm the sign-in. Please try again.', [], $previous);
    }
}
