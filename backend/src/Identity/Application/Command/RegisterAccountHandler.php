<?php

namespace App\Identity\Application\Command;

use App\Identity\Application\AccountRegistrar;
use App\Identity\Application\Port\PasswordHasher;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RegisterAccountHandler
{
    public function __construct(
        private readonly AccountRegistrar $registrar,
        private readonly PasswordHasher $hasher,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{customer_id: string, email: string, name: string} */
    public function __invoke(RegisterAccount $command): array
    {
        $user = $this->registrar->register($command->email, $command->name, $command->language, $command->source, 'password');
        $user->setPasswordHash($this->hasher->hash($command->password), $this->clock->now());

        return ['customer_id' => $user->customerId(), 'email' => $user->email(), 'name' => $user->name()];
    }
}
