<?php

namespace App\Organizations\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12. Counts one "organizations" (§7.2). Payload: {organization_id}. */
final class OrganizationCreated extends BaseDomainEvent
{
    public const FEATURE = 'organizations';

    public static function of(string $customerId, string $organizationId): self
    {
        return new self($customerId, self::FEATURE, ['organization_id' => $organizationId]);
    }
}
