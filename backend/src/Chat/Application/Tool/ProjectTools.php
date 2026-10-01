<?php

namespace App\Chat\Application\Tool;

use App\Assignations\Application\Command\CreateProject;
use App\Assignations\Application\Command\DeleteProject;
use App\Assignations\Application\Command\UpdateProject;
use App\Assignations\Application\Query\ProjectListCriteria;
use App\Assignations\Application\Query\ProjectQueries;
use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Text;

/**
 * PRD §7.19 "Projects: project CRUD", with the checks of PRD §8.9: writes need write permission, creating also
 * Feat(assignations); another account's project is 404.
 */
final class ProjectTools implements ChatToolbox
{
    public function __construct(
        private readonly ProjectQueries $projects,
        private readonly PlanGate $gate,
        private readonly CommandBus $commands,
    ) {
    }

    public function tools(): array
    {
        $fields = [
            'name' => Schema::string('The project\'s name.', 200),
            'description' => Schema::nullableString('A description (up to 2000 characters).'),
            'due_date' => Schema::string('The deadline (YYYY-MM-DD).'),
            'assignation_ids' => Schema::list(['type' => 'string'], 'Follow-up assignations of the organization that belong to the project (replaces the set).'),
        ];

        return [
            ChatTool::read(
                'list_projects',
                'Lists the account\'s projects, newest first, with their state and progress.',
                ['search' => Schema::string('Words that must all appear in the name.', 200)] + Schema::paging(),
                [],
                $this->list(...),
            ),
            ChatTool::read(
                'get_project',
                'One project with its assignations and their state.',
                ['project_id' => Schema::id('project')],
                ['project_id'],
                fn (Caller $caller, ToolInput $input): array => $this->owned($caller, $input->uuid('project_id')),
            ),
            ChatTool::write(
                'create_project',
                'Creates a project of one organization, with its follow-up assignations.',
                ['organization_id' => Schema::id('organization')] + $fields,
                ['organization_id', 'name', 'due_date'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);
                    $this->gate->feature($caller, Features::ASSIGNATIONS);
                    $input->uuid('organization_id');
                    $input->string('due_date', 10);

                    return $input->string('name');
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = (string) $this->commands->dispatch(new CreateProject(
                        $caller,
                        $input->uuid('organization_id'),
                        $input->string('name'),
                        $input->optionalString('description', 2000),
                        $input->string('due_date', 10),
                        array_values(array_filter((array) ($input->all()['assignation_ids'] ?? []), 'is_string')),
                    ));

                    return ['project_id' => $id];
                },
            ),
            ChatTool::write(
                'update_project',
                'Changes a project: only the fields sent (its organization never changes).',
                ['project_id' => Schema::id('project')] + $fields,
                ['project_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);

                    return (string) $this->owned($caller, $input->uuid('project_id'))['name'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = $input->uuid('project_id');
                    $this->commands->dispatch(new UpdateProject($caller, $id, $input->only(['name', 'description', 'due_date', 'assignation_ids'])));

                    return ['project_id' => $id];
                },
            ),
            ChatTool::write(
                'delete_project',
                'Deletes a project; its assignations and their answers are kept, they just stop belonging to it.',
                ['project_id' => Schema::id('project')],
                ['project_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);

                    return (string) $this->owned($caller, $input->uuid('project_id'))['name'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = $input->uuid('project_id');
                    $this->commands->dispatch(new DeleteProject($caller, $id));

                    return ['project_id' => $id, 'deleted' => true];
                },
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function list(Caller $caller, ToolInput $input): array
    {
        $size = $input->pageSize();
        $offset = intdiv($input->offset(), $size) * $size;
        $page = $this->projects->page(new ProjectListCriteria(
            $caller->isAdmin() ? null : $caller->customerId,
            null,
            Text::searchWords($input->optionalString('search') ?? ''),
            intdiv($offset, $size) + 1,
            $size,
        ));

        return Schema::page(array_map(static fn (array $p): array => [
            'project_id' => $p['project_id'],
            'name' => $p['name'],
            'organization_name' => $p['organization_name'],
            'state' => $p['state'],
            'due_date' => $p['due_date'],
            'approved_assignations' => $p['approved_assignations'],
            'total_assignations' => $p['total_assignations'],
        ], $page['items']), $offset, $page['total']);
    }

    /** @return array<string, mixed> */
    private function owned(Caller $caller, string $id): array
    {
        $project = $this->projects->find($id);
        if (null === $project || !$caller->owns((string) $project['customer_id'])) {
            throw new NotFound('PROJECT_NOT_FOUND', 'Project not found.');
        }

        return $project;
    }
}
