<?php

namespace App\Organizations\Application\Command;

use App\Organizations\Domain\Error\DomainEmailConflict;
use App\Organizations\Domain\Error\InvalidOrganization;
use App\Organizations\Domain\Error\OrganizationNotFound;
use App\Organizations\Domain\Event\OrganizationCreated;
use App\Organizations\Domain\MemberReconciliation;
use App\Organizations\Domain\MemberRules;
use App\Organizations\Domain\Model\MemberDraft;
use App\Organizations\Domain\Model\Organization;
use App\Organizations\Domain\Model\OrganizationUser;
use App\Organizations\Domain\OrganizationRules;
use App\Organizations\Domain\Repository\OrganizationRepository;
use App\Organizations\Domain\Repository\OrganizationUserRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SaveOrganizationHandler
{
    private const FIELDS = ['name', 'domain_email', 'description', 'active', 'organization_users'];

    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly OrganizationUserRepository $members,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SaveOrganization $command): string
    {
        $fields = array_intersect_key($command->fields, array_flip(self::FIELDS));
        $unknown = array_diff(array_keys($command->fields), self::FIELDS);
        if ([] !== $unknown) {
            throw new InvalidOrganization(array_map(static fn (int|string $f): array => ['field' => (string) $f, 'message' => 'This field was not expected.'], array_values($unknown)));
        }

        return null === $command->organizationId
            ? $this->create($command, $fields)
            : $this->update($command, $command->organizationId, $fields);
    }

    /** @param array<string, mixed> $fields */
    private function create(SaveOrganization $command, array $fields): string
    {
        if (!\is_string($fields['name'] ?? null)) {
            throw new InvalidOrganization([['field' => 'name', 'message' => 'Name is required.']]);
        }
        $name = OrganizationRules::normalizeName($fields['name']);
        $domain = OrganizationRules::normalizeDomain(self::nullableString($fields, 'domain_email'));
        $description = OrganizationRules::normalizeDescription(self::nullableString($fields, 'description'));
        $active = self::bool($fields, 'active', true);
        $drafts = self::drafts($fields['organization_users'] ?? []);
        $this->validate($name, $domain, $description, $drafts);
        $this->assertDomainIsFree($domain, null);

        $now = $this->clock->now();
        $organization = new Organization(Ids::uuid4(), $command->caller->customerId, $name, $now);
        $organization->describe($name, $domain, $description, $active, $now);
        $this->organizations->add($organization);
        foreach ($drafts as $draft) {
            $this->members->add($this->newMember($organization->organizationId(), $draft, $now));
        }
        $this->events->publish(OrganizationCreated::of($organization->customerId(), $organization->organizationId()));

        return $organization->organizationId();
    }

    /** @param array<string, mixed> $fields */
    private function update(SaveOrganization $command, string $organizationId, array $fields): string
    {
        $organization = $this->organizations->find($organizationId);
        if (null === $organization || !$command->caller->owns($organization->customerId())) {
            throw new OrganizationNotFound();
        }
        if ([] === $fields) {
            throw new InvalidOrganization([['field' => '', 'message' => 'Send at least one field to change.']]);
        }
        if (\array_key_exists('name', $fields) && !\is_string($fields['name'])) {
            throw new InvalidOrganization([['field' => 'name', 'message' => 'The name cannot be empty.']]);
        }

        $name = OrganizationRules::normalizeName(\is_string($fields['name'] ?? null) ? $fields['name'] : $organization->name());
        $domain = \array_key_exists('domain_email', $fields)
            ? OrganizationRules::normalizeDomain(self::nullableString($fields, 'domain_email'))
            : $organization->domainEmail();
        $description = \array_key_exists('description', $fields)
            ? OrganizationRules::normalizeDescription(self::nullableString($fields, 'description'))
            : $organization->description();
        $active = self::bool($fields, 'active', $organization->isActive());
        $drafts = \array_key_exists('organization_users', $fields) ? self::drafts($fields['organization_users']) : null;
        $this->validate($name, $domain, $description, $drafts ?? []);
        $this->assertDomainIsFree($domain, $organization);

        $now = $this->clock->now();
        $organization->describe($name, $domain, $description, $active, $now);
        if (null !== $drafts) {
            $this->reconcile($organization, $drafts, $now);
        }

        return $organization->organizationId();
    }

    /** @param list<MemberDraft> $drafts */
    private function reconcile(Organization $organization, array $drafts, \DateTimeImmutable $now): void
    {
        $plan = MemberReconciliation::plan($this->members->listByOrganization($organization->organizationId()), $drafts);
        $emailChanged = [];
        foreach ($plan->updates as [$member, $draft]) {
            if ($member->email() !== $draft->email) {
                $emailChanged[] = $member;
            }
        }
        $this->members->prepareReconciliation($plan->removals, $emailChanged);
        foreach ($plan->updates as [$member, $draft]) {
            $member->change($draft->name, $draft->email, $draft->phone, $draft->role, $draft->area, $now);
        }
        foreach ($plan->creates as $draft) {
            $this->members->add($this->newMember($organization->organizationId(), $draft, $now));
        }
    }

    /** @param list<MemberDraft> $drafts */
    private function validate(string $name, ?string $domain, ?string $description, array $drafts): void
    {
        $violations = [...OrganizationRules::violations($name, $domain, $description), ...MemberRules::violations($drafts)];
        if ([] !== $violations) {
            throw new InvalidOrganization($violations);
        }
    }

    private function assertDomainIsFree(?string $domain, ?Organization $self): void
    {
        if (null === $domain) {
            return;
        }
        $owner = $this->organizations->findByDomain($domain);
        if (null !== $owner && $owner !== $self) {
            throw new DomainEmailConflict($domain);
        }
    }

    private function newMember(string $organizationId, MemberDraft $draft, \DateTimeImmutable $now): OrganizationUser
    {
        // Ids sent for new members are not trusted: a new member always gets a fresh id.
        return new OrganizationUser(Ids::uuid4(), $organizationId, $draft->name, $draft->email, $draft->phone, $draft->role, $draft->area, $now);
    }

    /** @return list<MemberDraft> */
    private static function drafts(mixed $members): array
    {
        if (!\is_array($members) || !array_is_list($members)) {
            throw new InvalidOrganization([['field' => 'organization_users', 'message' => 'This value should be a list of members.']]);
        }
        $drafts = [];
        foreach ($members as $index => $member) {
            if (!\is_array($member)) {
                throw new InvalidOrganization([['field' => "organization_users[$index]", 'message' => 'This value should be a member.']]);
            }
            $drafts[] = MemberDraft::of($member);
        }

        return $drafts;
    }

    /** @param array<string, mixed> $fields */
    private static function nullableString(array $fields, string $key): ?string
    {
        $value = $fields[$key] ?? null;
        if (null !== $value && !\is_string($value)) {
            throw new InvalidOrganization([['field' => $key, 'message' => 'This value should be of type string.']]);
        }

        return $value;
    }

    /** @param array<string, mixed> $fields */
    private static function bool(array $fields, string $key, bool $default): bool
    {
        if (!\array_key_exists($key, $fields)) {
            return $default;
        }
        if (!\is_bool($fields[$key])) {
            throw new InvalidOrganization([['field' => $key, 'message' => 'This value should be of type bool.']]);
        }

        return $fields[$key];
    }
}
