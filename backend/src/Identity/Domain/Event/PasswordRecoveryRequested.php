<?php

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/**
 * Someone asked to recover the password of an existing user (PRD §8.2). The code is created and emailed by the
 * event's handler, so the plain code never travels in a queued message or the event log. Payload: {email}.
 */
final class PasswordRecoveryRequested extends BaseDomainEvent
{
    public static function of(string $customerId, string $email): self
    {
        return new self($customerId, ['email' => $email]);
    }

    public function email(): string
    {
        return (string) ($this->payload()['email'] ?? '');
    }
}
