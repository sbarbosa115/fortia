<?php

namespace App\Identity\Infrastructure\Google;

use App\Identity\Application\Port\GoogleIdentity;
use App\Identity\Application\Port\GoogleProfile;
use App\Identity\Domain\Error\GoogleSignInFailed;

/**
 * The test double of Google sign-in (wired in the test environment only). An authorization code
 * "fake|<email>|<name>|<locale>|<subject>" is a verified Google user; anything else is refused. Tests can turn
 * $configured off to see PROVIDER_NOT_CONFIGURED.
 */
final class FakeGoogleIdentity implements GoogleIdentity
{
    public bool $configured = true;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return 'https://accounts.example.test/auth?'.http_build_query(['state' => $state, 'code_challenge' => $codeChallenge, 'redirect_uri' => $redirectUri]);
    }

    public function exchange(string $code, string $codeVerifier, string $redirectUri): GoogleProfile
    {
        $parts = explode('|', $code);
        if (5 !== \count($parts) || 'fake' !== $parts[0] || '' === $codeVerifier) {
            throw new GoogleSignInFailed();
        }

        return new GoogleProfile($parts[4], $parts[1], $parts[2], '' === $parts[3] ? null : $parts[3]);
    }
}
