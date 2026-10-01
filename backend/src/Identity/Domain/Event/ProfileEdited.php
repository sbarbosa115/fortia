<?php

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** The account settings changed (PRD §8.3 PATCH settings). Payload: {fields}. */
final class ProfileEdited extends BaseDomainEvent
{
    /** @param list<string> $fields */
    public static function of(string $customerId, array $fields): self
    {
        return new self($customerId, ['fields' => $fields]);
    }
}
