<?php

namespace App\Identity\UI\Http\Output;

/** Google's consent screen, where the console sends the browser (PRD §10.2 "Continue with Google"). */
final class GoogleAuthorizationOutput
{
    public function __construct(public readonly string $authorization_url)
    {
    }
}
