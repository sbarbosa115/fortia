<?php

namespace App\Identity\Application\Command;

use App\Identity\Application\Port\TokenIssuer;
use App\Identity\Application\TokenPair;
use App\Identity\Domain\Repository\RefreshTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\Unauthenticated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RefreshSessionHandler
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly UserRepository $users,
        private readonly TokenIssuer $tokens,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RefreshSession $command): TokenPair
    {
        $stored = $this->refreshTokens->findByHash(hash('sha256', $command->refreshToken));
        $user = null === $stored ? null : $this->users->find($stored->userId());
        if (null === $stored || null === $user || $stored->isExpiredAt($this->clock->now())) {
            throw new Unauthenticated('UNAUTHORIZED', 'The session has expired. Sign in again.');
        }
        $this->refreshTokens->remove($stored);

        return $this->tokens->issue($user);
    }
}
