<?php

namespace App\Assignations\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12. Counts one "assignations" (§7.2). Payload: {assignation_id}. */
final class AssignationDeleted extends BaseDomainEvent
{
    public const FEATURE = 'assignations';

    public static function of(string $customerId, string $assignationsId): self
    {
        return new self($customerId, self::FEATURE, ['assignation_id' => $assignationsId]);
    }
}
