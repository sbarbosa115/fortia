<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

/** A new id token for a valid refresh token; the refresh token is rotated. Returns a TokenPair. */
final class RefreshSession
{
    public function __construct(public readonly string $refreshToken)
    {
    }
}
