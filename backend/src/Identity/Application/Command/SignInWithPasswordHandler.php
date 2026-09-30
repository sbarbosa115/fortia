<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Application\Port\TokenIssuer;
use App\Identity\Application\TokenPair;
use App\Identity\Domain\Event\UserSignedIn;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\Unauthenticated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SignInWithPasswordHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SignInWithPassword $command): TokenPair
    {
        $user = $this->users->findByEmail($command->email);
        $hash = $user?->passwordHash();
        // The same answer for an unknown email and a wrong password (PRD §10.2 "Invalid email or password.").
        if (null === $user || null === $hash || !$this->hasher->verify($hash, $command->password)) {
            throw new Unauthenticated('INVALID_CREDENTIALS', 'Invalid email or password.');
        }
        $user->recordSignIn($this->clock->now());
        $this->events->publish(new UserSignedIn($user->customerId(), null, ['email' => $user->email()]));

        return $this->tokens->issue($user);
    }
}
