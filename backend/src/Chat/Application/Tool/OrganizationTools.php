<?php

namespace App\Chat\Application\Tool;

use App\Organizations\Application\Command\DeleteOrganization;
use App\Organizations\Application\Command\SaveOrganization;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Text;

/**
 * PRD §7.19 "Organizations: organization CRUD", with the checks of PRD §8.7: creating is AG;
 * changing and deleting need write permission and the caller's own organization (another account's is 404, D1).
 */
final class OrganizationTools implements ChatToolbox
{
    private const FIELDS = ['name', 'domain_email', 'description', 'active', 'organization_users'];

    public function __construct(
        private readonly OrganizationQueries $organizations,
        private readonly CommandBus $commands,
    ) {
    }

    public function tools(): array
    {
        $member = Schema::object([
            'organization_user_id' => Schema::string('The member\'s id, to keep an existing member.'),
            'name' => Schema::string('Full name.', 200),
            'email' => Schema::string('Email.', 255),
            'phone' => Schema::string('Phone.', 50),
            'role' => Schema::string('Role.', 120),
            'area' => Schema::string('Area.', 120),
        ]);
        $fields = [
            'name' => Schema::string('The organization\'s name.', 200),
            'domain_email' => Schema::nullableString('The email domain of its members, e.g. "acme.com".'),
            'description' => Schema::nullableString('A description.'),
            'active' => Schema::bool('Whether it is active.'),
            'organization_users' => Schema::list($member, 'Its members. On a change it replaces the list: send every member to keep (with organization_user_id).'),
        ];

        return [
            ChatTool::read(
                'list_organizations',
                'Lists the account\'s organizations, newest first, with how many members each has.',
                ['search' => Schema::string('Words that must all appear in the name.', 200)] + Schema::paging(),
                [],
                $this->list(...),
            ),
            ChatTool::read(
                'get_organization',
                'One organization with its members.',
                ['organization_id' => Schema::id('organization')],
                ['organization_id'],
                fn (Caller $caller, ToolInput $input): array => $this->owned($caller, $input->uuid('organization_id')),
            ),
            ChatTool::write(
                'create_organization',
                'Creates an organization (and its members).',
                $fields,
                ['name'],
                static function (Caller $caller, ToolInput $input): string {
                    Permissions::adminGroups($caller);

                    return $input->string('name');
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = (string) $this->commands->dispatch(SaveOrganization::create($caller, $input->only(self::FIELDS)));

                    return ['organization_id' => $id, 'name' => $input->string('name')];
                },
            ),
            ChatTool::write(
                'update_organization',
                'Changes an organization: only the fields sent.',
                ['organization_id' => Schema::id('organization')] + $fields,
                ['organization_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);

                    return (string) $this->owned($caller, $input->uuid('organization_id'))['name'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = $input->uuid('organization_id');
                    $this->commands->dispatch(SaveOrganization::update($caller, $id, $input->only(self::FIELDS)));

                    return ['organization_id' => $id];
                },
            ),
            ChatTool::write(
                'delete_organization',
                'Deletes an organization and its members. Refused while assignations or projects use it.',
                ['organization_id' => Schema::id('organization')],
                ['organization_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);

                    return (string) $this->owned($caller, $input->uuid('organization_id'))['name'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = $input->uuid('organization_id');
                    $this->commands->dispatch(new DeleteOrganization($caller, $id));

                    return ['organization_id' => $id, 'deleted' => true];
                },
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function list(Caller $caller, ToolInput $input): array
    {
        $search = $input->optionalString('search') ?? '';
        $rows = array_values(array_filter(
            $this->organizations->listFor($caller->isAdmin() ? null : $caller->customerId),
            static fn (array $o): bool => '' === $search || Text::matchesAllWords((string) $o['name'], $search),
        ));
        $offset = $input->offset();

        return Schema::page(array_map(static fn (array $o): array => [
            'organization_id' => $o['organization_id'],
            'name' => $o['name'],
            'domain_email' => $o['domain_email'],
            'active' => $o['active'],
            'members' => \count((array) $o['organization_users']),
            'created_at' => $o['created_at'],
        ], \array_slice($rows, $offset, $input->pageSize())), $offset, \count($rows));
    }

    /** @return array<string, mixed> */
    private function owned(Caller $caller, string $id): array
    {
        $organization = $this->organizations->find($id);
        if (null === $organization || !$caller->owns((string) $organization['customer_id'])) {
            throw new NotFound('ORGANIZATION_NOT_FOUND', 'Organization not found.');
        }
        $organization['organization_users'] = \array_slice((array) $organization['organization_users'], 0, 100);

        return $organization;
    }
}
