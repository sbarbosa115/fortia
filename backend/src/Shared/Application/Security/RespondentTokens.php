<?php

declare(strict_types=1);

namespace App\Shared\Application\Security;

/**
 * The assignation respondent token (PRD §7.11, §13.1): signed by the API with its own secret, binding the
 * assignation, the member and the session. It expires (D6: 30 days by default).
 */
interface RespondentTokens
{
    public function issue(string $assignationsId, string $organizationUserId, string $sessionId): string;

    /** The claims of a valid, unexpired token; null for anything else. */
    public function parse(string $token): ?RespondentClaims;
}
