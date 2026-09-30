<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\CustomerRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Domain\Iso;

/**
 * Reads of accounts for other contexts: the account's language and settings (emails, respondent screens, file
 * limits) and its users (root users receive the follow-up status emails, PRD §7.13).
 */
final class AccountQueries
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @return array{customer_id: string, language: string, source: string, settings: array<string, mixed>, onboarding_completed: bool|null, workspace_name: string|null, website: string|null, created_at: string|null}|null
     */
    public function find(string $customerId): ?array
    {
        $customer = $this->customers->find($customerId);
        if (null === $customer) {
            return null;
        }

        return [
            'customer_id' => $customer->customerId(),
            'language' => $customer->language(),
            'source' => $customer->source(),
            'settings' => $customer->settings(),
            'onboarding_completed' => $customer->onboardingCompleted(),
            'workspace_name' => $customer->workspaceName(),
            'website' => $customer->website(),
            'created_at' => Iso::datetime($customer->createdAt()),
        ];
    }

    public function exists(string $customerId): bool
    {
        return null !== $this->customers->find($customerId);
    }

    /** @return array{email: string, name: string}|null the account owner */
    public function rootUserOf(string $customerId): ?array
    {
        $root = $this->users->findRootOf($customerId);

        return null === $root ? null : ['email' => $root->email(), 'name' => $root->name()];
    }

    /**
     * @return list<array{email: string, name: string, root: bool, role: string, customer_id: string}> root first,
     *                                                                                                  then by name
     */
    public function usersOf(string $customerId): array
    {
        return array_map(static fn (User $u): array => [
            'email' => $u->email(),
            'name' => $u->name(),
            'root' => $u->isRoot(),
            'role' => $u->displayedRole(),
            'customer_id' => $u->customerId(),
        ], $this->users->listByCustomer($customerId));
    }
}
