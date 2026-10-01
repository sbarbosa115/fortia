<?php

namespace App\Identity\Application;

use App\Identity\Application\Port\GoogleIdentity;
use App\Identity\Domain\Error\ProviderNotConfigured;

/**
 * Where Google sends the browser back (the console's /sign-in page) and the consent URL the console redirects to.
 */
final class GoogleSignIn
{
    public function __construct(
        private readonly GoogleIdentity $google,
        private readonly string $adminFrontendUrl,
    ) {
    }

    public function redirectUri(): string
    {
        return rtrim($this->adminFrontendUrl, '/').'/sign-in';
    }

    /** @throws ProviderNotConfigured */
    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        if (!$this->google->isConfigured()) {
            throw new ProviderNotConfigured();
        }

        return $this->google->authorizationUrl($state, $codeChallenge, $this->redirectUri());
    }
}
