<?php

namespace App\Identity\Application\Port;

use App\Identity\Domain\Error\GoogleSignInFailed;

/**
 * Sign-in federated with Google (PRD §13.1): OAuth code flow with PKCE, scopes openid, email and profile. The real
 * adapter needs GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET; without them isConfigured() is false and the
 * API answers 503 PROVIDER_NOT_CONFIGURED.
 */
interface GoogleIdentity
{
    public function isConfigured(): bool;

    /** Where to send the browser: Google's consent screen, returning to $redirectUri with ?code and ?state. */
    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string;

    /**
     * Exchanges the authorization code for the Google user's verified identity.
     *
     * @throws GoogleSignInFailed
     */
    public function exchange(string $code, string $codeVerifier, string $redirectUri): GoogleProfile;
}
