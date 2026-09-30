<?php

namespace App\Shared\Application\Security;

final class RespondentClaims
{
    public function __construct(
        public readonly string $assignationsId,
        public readonly string $organizationUserId,
        public readonly string $sessionId,
    ) {
    }
}
