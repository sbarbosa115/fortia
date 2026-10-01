<?php

namespace App\Identity\Application;

use App\Billing\Application\Command\AssignStarterPlan;
use App\Identity\Domain\Error\EmailAlreadyExists;
use App\Identity\Domain\Event\UserRootRegistered;
use App\Identity\Domain\Model\Customer;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\CustomerRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;

/**
 * Opens a new account (PRD §7.3), for a native sign-up and a first Google login alike: a new customer_id, the root
 * user in Customer-Admin, onboarding pending, the starter plan for a month (Billing), and UserRootRegistered. Called
 * inside a command handler, so it all commits together.
 */
final class AccountRegistrar
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly UserRepository $users,
        private readonly CommandBus $commands,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    /** @throws EmailAlreadyExists */
    public function register(string $email, string $name, string $language, string $source, string $via): User
    {
        $email = EmailAddress::normalize($email);
        if (null !== $this->users->findByEmail($email)) {
            throw new EmailAlreadyExists();
        }
        $now = $this->clock->now();
        $customerId = $this->newCustomerId();
        $this->customers->add(new Customer($customerId, $language, $source, $now, false));
        $user = new User(Ids::uuid4(), $email, mb_substr(trim($name), 0, 50), $customerId, true, [Caller::CUSTOMER_ADMIN], $now);
        $this->users->add($user);
        $this->commands->dispatch(new AssignStarterPlan($customerId));
        $this->events->publish(UserRootRegistered::of($customerId, $user->email(), $user->name(), $language, $via));

        return $user;
    }

    private function newCustomerId(): string
    {
        do {
            $id = Ids::customerId();
        } while (null !== $this->customers->find($id));

        return $id;
    }
}
