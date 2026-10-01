<?php

namespace App\Identity\Application;

/** The outcome of a Google sign-in: a session, or the account was linked and the user must retry (PRD §13.1). */
final class GoogleSignInResult
{
    private function __construct(public readonly ?TokenPair $tokens)
    {
    }

    public static function signedIn(TokenPair $tokens): self
    {
        return new self($tokens);
    }

    public static function linked(): self
    {
        return new self(null);
    }
}
