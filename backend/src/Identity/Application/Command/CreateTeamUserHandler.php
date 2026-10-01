<?php

namespace App\Identity\Application\Command;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Error\EmailAlreadyExists;
use App\Identity\Domain\Error\InvalidRole;
use App\Identity\Domain\Event\UserCreated;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateTeamUserHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{email: string, name: string, root: bool, role: string, customer_id: string} */
    public function __invoke(CreateTeamUser $command): array
    {
        if (!\in_array($command->role, Caller::ASSIGNABLE_ROLES, true)) {
            throw new InvalidRole();
        }
        $email = EmailAddress::normalize($command->email);
        if (null !== $this->users->findByEmail($email)) {
            throw new EmailAlreadyExists();
        }
        $now = $this->clock->now();
        $user = new User(Ids::uuid4(), $email, trim($command->name), $command->customerId, false, [$command->role], $now);
        $user->setPasswordHash($this->hasher->hash($command->password), $now);
        $this->users->add($user);
        $this->events->publish(UserCreated::of($command->customerId, $user->email(), $command->role));

        return ['email' => $user->email(), 'name' => $user->name(), 'root' => false, 'role' => $user->displayedRole(), 'customer_id' => $user->customerId()];
    }
}
