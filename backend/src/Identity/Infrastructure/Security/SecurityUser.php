<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** The framework's view of a console user. Controllers never see it: they get a Caller. */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param list<string> $groups
     */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $name,
        public readonly string $customerId,
        public readonly bool $root,
        public readonly array $groups,
        private readonly ?string $passwordHash,
    ) {
    }

    public static function of(User $user): self
    {
        return new self($user->id(), $user->email(), $user->name(), $user->customerId(), $user->isRoot(), $user->groups(), $user->passwordHash());
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        if (\in_array('Admin', $this->groups, true)) {
            $roles[] = 'ROLE_ADMIN';
        }

        return $roles;
    }

    public function getUserIdentifier(): string
    {
        return '' === $this->email ? $this->id : $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
    }
}
