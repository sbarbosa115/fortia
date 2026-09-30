<?php

namespace App\Identity\UI\Http\Output;

use App\Identity\Application\TokenPair;

/** The console's session: an id token (Bearer, 24 h) and a refresh token (30 days). */
final class TokenOutput
{
    public function __construct(
        public readonly string $id_token,
        public readonly string $refresh_token,
        public readonly int $expires_in,
    ) {
    }

    public static function of(TokenPair $pair): self
    {
        return new self($pair->idToken, $pair->refreshToken, $pair->expiresIn);
    }
}
