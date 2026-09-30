<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Seed;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Model\Customer;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\CustomerRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Seed\DemoAccounts;
use App\Shared\Application\Seed\DemoSeeder;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;

/** The demo accounts and their users (DemoAccounts). */
final class AccountsSeeder implements DemoSeeder
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Clock $clock,
    ) {
    }

    public static function priority(): int
    {
        return 90;
    }

    public function seed(): void
    {
        $now = $this->clock->now();
        $hash = $this->hasher->hash(DemoAccounts::PASSWORD);
        $created = [];
        foreach (DemoAccounts::users() as $row) {
            if (!isset($created[$row['customer_id']]) && null === $this->customers->find($row['customer_id'])) {
                $this->customers->add(new Customer($row['customer_id'], $row['language'], 'default', $now, $row['onboarding']));
                $created[$row['customer_id']] = true;
            }
            if (null === $this->users->findByEmail($row['email'])) {
                $user = new User(Ids::uuid4(), $row['email'], $row['name'], $row['customer_id'], $row['root'], $row['groups'], $now);
                $user->setPasswordHash($hash, $now);
                $this->users->add($user);
            }
        }
    }
}
