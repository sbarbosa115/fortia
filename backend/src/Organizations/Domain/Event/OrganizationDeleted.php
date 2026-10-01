<?php

namespace App\Organizations\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12. Payload: {organization_id, members_deleted}. */
final class OrganizationDeleted extends BaseDomainEvent
{
    public static function of(string $customerId, string $organizationId, int $membersDeleted): self
    {
        return new self($customerId, ['organization_id' => $organizationId, 'members_deleted' => $membersDeleted]);
    }
}
