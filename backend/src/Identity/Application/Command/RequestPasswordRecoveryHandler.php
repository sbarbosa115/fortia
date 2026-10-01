<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Event\PasswordRecoveryRequested;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RequestPasswordRecoveryHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EventBus $events,
    ) {
    }

    public function __invoke(RequestPasswordRecovery $command): void
    {
        $user = $this->users->findByEmail(EmailAddress::normalize($command->email));
        if (null !== $user) {
            $this->events->publish(PasswordRecoveryRequested::of($user->customerId(), $user->email()));
        }
    }
}
