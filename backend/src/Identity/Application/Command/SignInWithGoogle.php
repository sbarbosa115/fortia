<?php

namespace App\Identity\Application\Command;

/**
 * The return from Google's consent screen (PRD §10.2 /sign-in, §13.1): the authorization code and the PKCE verifier.
 * Returns a GoogleSignInResult: the session, or "linked, retry" when the Google account was just linked to an
 * existing password account (the link commits, so the retry signs in).
 */
final class SignInWithGoogle
{
    public function __construct(
        #[\SensitiveParameter]
        public readonly string $code,
        #[\SensitiveParameter]
        public readonly string $codeVerifier,
    ) {
    }
}
