<?php

namespace App\Organizations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * Creates an organization with its members, or changes one (PRD §8.7). Returns the organization id.
 *
 *     $id = $commands->dispatch(SaveOrganization::create($caller, ['name' => 'Acme', 'organization_users' => [...]]));
 *     $commands->dispatch(SaveOrganization::update($caller, $id, ['active' => false]));
 *
 * $fields holds only what is set, with the PRD's field names: name, domain_email, description, active and
 * organization_users ([{organization_user_id?, name, email?, phone?, role?, area?}]). On update, sending
 * organization_users reconciles the members (by id, then email, then name + phone; the others are deleted).
 *
 * The handler checks the domain rules (400 VALIDATION_ERROR, 409 DOMAIN_EMAIL_CONFLICT) and ownership on update
 * (404 ORGANIZATION_NOT_FOUND for another account's organization, unless the caller is an Admin). It does NOT run the
 * plan gate or the role check: the caller does, before dispatching — PlanGate::capacity($caller,
 * Features::ORGANIZATIONS) on create, and write permission. A create publishes OrganizationCreated (counts usage).
 */
final class SaveOrganization
{
    /**
     * @param array<string, mixed> $fields
     */
    private function __construct(
        public readonly Caller $caller,
        public readonly ?string $organizationId,
        public readonly array $fields,
    ) {
    }

    /** @param array<string, mixed> $fields name is required; active defaults to true */
    public static function create(Caller $caller, array $fields): self
    {
        return new self($caller, null, $fields);
    }

    /** @param array<string, mixed> $fields at least one field */
    public static function update(Caller $caller, string $organizationId, array $fields): self
    {
        return new self($caller, $organizationId, $fields);
    }
}
