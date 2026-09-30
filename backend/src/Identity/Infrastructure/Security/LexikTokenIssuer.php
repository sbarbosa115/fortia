<?php

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\TokenIssuer;
use App\Identity\Application\TokenPair;
use App\Identity\Domain\Model\RefreshToken;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\RefreshTokenRepository;
use App\Shared\Domain\Clock;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class LexikTokenIssuer implements TokenIssuer
{
    private const ID_TOKEN_TTL = 86400;
    private const REFRESH_TTL_DAYS = 30;

    public function __construct(
        private readonly JWTTokenManagerInterface $jwt,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly Clock $clock,
    ) {
    }

    public function issue(User $user): TokenPair
    {
        $refresh = bin2hex(random_bytes(32));
        $now = $this->clock->now();
        $this->refreshTokens->add(new RefreshToken(hash('sha256', $refresh), $user->id(), $now->modify(\sprintf('+%d days', self::REFRESH_TTL_DAYS)), $now));

        return new TokenPair($this->jwt->create(SecurityUser::of($user)), $refresh, self::ID_TOKEN_TTL);
    }
}
