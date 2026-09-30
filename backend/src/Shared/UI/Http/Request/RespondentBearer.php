<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\Request;

use App\Shared\Application\Security\RespondentClaims;
use App\Shared\Application\Security\RespondentTokens;
use App\Shared\Domain\Error\Unauthenticated;
use Symfony\Component\HttpFoundation\Request;

/**
 * The assignation respondent token of a request ("Authorization: Bearer rt.…", PRD §7.11). A token that is present
 * but invalid or expired is refused with 401 (D7), never silently ignored.
 */
final class RespondentBearer
{
    public function __construct(private readonly RespondentTokens $tokens)
    {
    }

    /** The claims, or null when the request carries no respondent token. */
    public function claims(Request $request): ?RespondentClaims
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));
        if (!str_starts_with($token, 'rt.')) {
            return null;
        }

        return $this->tokens->parse($token) ?? throw new Unauthenticated('UNAUTHORIZED', 'The respondent token is not valid.');
    }
}
