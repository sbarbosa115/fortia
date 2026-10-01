<?php

namespace App\Assignations\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12. Payload: {assignation_id}. */
final class AssignationCreated extends BaseDomainEvent
{
    public static function of(string $customerId, string $assignationsId): self
    {
        return new self($customerId, ['assignation_id' => $assignationsId]);
    }
}
