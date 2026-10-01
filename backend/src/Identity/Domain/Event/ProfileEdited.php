<?php

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/**
 * The account settings changed (PRD §8.3 PATCH settings). It counts one unit of "profile" (§7.2), except when only
 * the language changed. Payload: {fields}.
 */
final class ProfileEdited extends BaseDomainEvent
{
    public const FEATURE = 'profile';

    /** @param list<string> $fields */
    public static function of(string $customerId, array $fields, bool $counts): self
    {
        return new self($customerId, $counts ? self::FEATURE : null, ['fields' => $fields]);
    }
}
