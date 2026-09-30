<?php

namespace App\Organizations\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12. Counts one "organizations" (§7.2: create or delete). Payload: {organization_id, members_deleted}. */
final class OrganizationDeleted extends BaseDomainEvent
{
    public static function of(string $customerId, string $organizationId, int $membersDeleted): self
    {
        return new self($customerId, OrganizationCreated::FEATURE, ['organization_id' => $organizationId, 'members_deleted' => $membersDeleted]);
    }
}
