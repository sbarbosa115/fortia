<?php

declare(strict_types=1);

namespace App\Identity\Application;

final class TokenPair
{
    public function __construct(
        public readonly string $idToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
    ) {
    }
}
