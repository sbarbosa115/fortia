<?php

namespace App\Identity\Infrastructure\Google;

use App\Identity\Application\Port\GoogleIdentity;
use App\Identity\Application\Port\GoogleProfile;
use App\Identity\Domain\Error\GoogleSignInFailed;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google's OAuth 2.0 code flow with PKCE (PRD §13.1): the consent URL, then the code exchanged at the token
 * endpoint and the user read from the OpenID userinfo endpoint (only a verified email is accepted). Configured by
 * GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET; without them, isConfigured() is false.
 */
final class OAuthGoogleIdentity implements GoogleIdentity
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire(env: 'GOOGLE_OAUTH_CLIENT_ID')]
        private readonly string $clientId,
        #[\SensitiveParameter]
        #[Autowire(env: 'GOOGLE_OAUTH_CLIENT_SECRET')]
        private readonly string $clientSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->clientId) && '' !== trim($this->clientSecret);
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $codeVerifier, string $redirectUri): GoogleProfile
    {
        try {
            $token = $this->http->request('POST', self::TOKEN_URL, [
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'code_verifier' => $codeVerifier,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $redirectUri,
                ],
                'timeout' => 10,
            ])->toArray();
            $accessToken = (string) ($token['access_token'] ?? '');
            if ('' === $accessToken) {
                throw new GoogleSignInFailed();
            }
            $info = $this->http->request('GET', self::USERINFO_URL, [
                'auth_bearer' => $accessToken,
                'timeout' => 10,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw new GoogleSignInFailed($e);
        }

        $email = \is_string($info['email'] ?? null) ? $info['email'] : '';
        $subject = \is_scalar($info['sub'] ?? null) ? (string) $info['sub'] : '';
        if ('' === $email || '' === $subject || true !== ($info['email_verified'] ?? false)) {
            throw new GoogleSignInFailed();
        }

        return new GoogleProfile(
            $subject,
            $email,
            \is_string($info['name'] ?? null) ? $info['name'] : '',
            \is_string($info['locale'] ?? null) ? $info['locale'] : null,
        );
    }
}
