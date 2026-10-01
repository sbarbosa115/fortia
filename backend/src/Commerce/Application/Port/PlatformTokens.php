<?php

namespace App\Commerce\Application\Port;

/** The e-commerce platform's tokens for one store. They are secrets: never returned by the API. */
final class PlatformTokens
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly ?\DateTimeImmutable $expiresAt,
    ) {
    }
}
