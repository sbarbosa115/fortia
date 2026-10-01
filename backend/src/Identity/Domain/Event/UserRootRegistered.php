<?php

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/**
 * A new account and its root user (PRD §8.2 POST /register, or a first Google login §13.1). Payload: {email, name,
 * language, via}. The welcome email is sent on it (D20).
 */
final class UserRootRegistered extends BaseDomainEvent
{
    public static function of(string $customerId, string $email, string $name, string $language, string $via): self
    {
        return new self($customerId, null, ['email' => $email, 'name' => $name, 'language' => $language, 'via' => $via]);
    }
}
