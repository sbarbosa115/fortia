<?php

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** A team user created with POST /users (PRD §8.2). Payload: {email, role}. */
final class UserCreated extends BaseDomainEvent
{
    public static function of(string $customerId, string $email, string $role): self
    {
        return new self($customerId, ['email' => $email, 'role' => $role]);
    }
}
