<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Infrastructure\Security\JwtRespondentTokens;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Symfony\Component\HttpFoundation\Request;

/**
 * The console's id-token authenticator. A respondent token ("rt." prefix, PRD §7.11) on the same Authorization
 * header is not a console sign-in: it is left to the respondent endpoints, which read it themselves.
 */
final class ConsoleJwtAuthenticator extends JWTAuthenticator
{
    public function supports(Request $request): ?bool
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (str_starts_with($header, 'Bearer '.JwtRespondentTokens::PREFIX)) {
            return false;
        }

        return parent::supports($request);
    }
}
