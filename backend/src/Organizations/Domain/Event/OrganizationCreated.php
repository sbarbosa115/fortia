<?php

namespace App\Organizations\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12. Payload: {organization_id}. */
final class OrganizationCreated extends BaseDomainEvent
{
    public static function of(string $customerId, string $organizationId): self
    {
        return new self($customerId, ['organization_id' => $organizationId]);
    }
}
