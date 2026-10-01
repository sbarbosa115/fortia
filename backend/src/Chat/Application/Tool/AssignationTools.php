<?php

namespace App\Chat\Application\Tool;

use App\Assignations\Application\Command\CreateAssignation;
use App\Assignations\Application\Command\DeleteAssignation;
use App\Assignations\Application\Command\ReminderOutcome;
use App\Assignations\Application\Command\SendReminder;
use App\Assignations\Application\Command\UpdateAssignation;
use App\Assignations\Application\Query\AssignationDetails;
use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Identity\Application\Query\AccountQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotFound;

/**
 * PRD §7.19 "Assignations: assignation CRUD, respondents, send_follow_up_reminder", with the checks of PRD §8.8:
 * creating is AG and Cap(assignations); changing, deleting and reminding need write permission; another account's
 * assignation is 404. An assignation made in the chat gets the default registration slide (full name and email,
 * both required, PRD §10.11) unless the model sends one.
 */
final class AssignationTools implements ChatToolbox
{
    private const FIELDS = ['organization_id', 'questionnaire_id', 'name', 'description', 'max_follow_ups', 'active', 'type', 'due_date', 'audience', 'questions'];
    private const REGISTRATION_TITLE = ['es' => 'Tus datos', 'en' => 'Your details'];

    public function __construct(
        private readonly AssignationDetails $details,
        private readonly AccountQueries $accounts,
        private readonly PlanGate $gate,
        private readonly CommandBus $commands,
    ) {
    }

    public function tools(): array
    {
        $audience = Schema::object([
            'type' => Schema::enum(['all', 'members', 'area', 'role'], 'Who answers: every member, some members (values = their ids), an area or a role (values = the names).'),
            'values' => Schema::list(['type' => 'string'], 'The members\' ids, areas or roles.'),
        ], 'Who answers (default: every member of the organization).');
        $fields = [
            'organization_id' => Schema::id('organization'),
            'questionnaire_id' => Schema::id('questionnaire'),
            'name' => Schema::string('The assignation\'s name.', 200),
            'description' => Schema::nullableString('An internal description.'),
            'type' => Schema::enum(['default', 'follow_up'], 'default: each member answers once; follow_up: one shared answer reviewed by the account, with retries.'),
            'max_follow_ups' => Schema::int('How many retries a follow-up allows (e.g. 2; 0 for a default one).'),
            'due_date' => Schema::nullableString('Follow-ups only: the due date (YYYY-MM-DD).'),
            'active' => Schema::bool('Whether respondents can answer.'),
            'audience' => $audience,
        ];

        return [
            ChatTool::read(
                'list_assignations',
                'Lists the account\'s assignations, newest first.',
                ['type' => Schema::enum(['default', 'follow_up'], 'Only this type.'), 'questionnaire_id' => Schema::id('questionnaire')] + Schema::paging(),
                [],
                $this->list(...),
            ),
            ChatTool::read(
                'get_assignation',
                'One assignation: its organization, questionnaire, link, audience and progress.',
                ['assignation_id' => Schema::id('assignation')],
                ['assignation_id'],
                fn (Caller $caller, ToolInput $input): array => self::summary($this->owned($caller, $input->uuid('assignation_id'))),
            ),
            ChatTool::read(
                'list_assignation_respondents',
                'The respondents of an assignation, by name, with each one\'s status (pending, in_progress, completed).',
                ['assignation_id' => Schema::id('assignation')] + Schema::paging(),
                ['assignation_id'],
                $this->respondents(...),
            ),
            ChatTool::write(
                'create_assignation',
                'Sends a questionnaire to an organization\'s members.',
                $fields,
                ['organization_id', 'questionnaire_id', 'name', 'type'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::adminGroups($caller);
                    $input->uuid('organization_id');
                    $input->uuid('questionnaire_id');
                    $input->choice('type', ['default', 'follow_up']);
                    $this->gate->capacity($caller, Features::ASSIGNATIONS);

                    return $input->string('name');
                },
                function (Caller $caller, ToolInput $input): array {
                    $fields = $input->only(self::FIELDS);
                    $fields['max_follow_ups'] ??= 'follow_up' === $fields['type'] ? 2 : 0;
                    $fields['questions'] ??= [$this->registration($caller)];
                    $id = (string) $this->commands->dispatch(new CreateAssignation($caller, $fields));

                    return ['assignation_id' => $id, 'url' => $this->details->find($id, true)['questionnaire_url'] ?? null];
                },
            ),
            ChatTool::write(
                'update_assignation',
                'Changes an assignation: only the fields sent (its type never changes; due_date null clears it).',
                ['assignation_id' => Schema::id('assignation')] + array_diff_key($fields, ['type' => true]),
                ['assignation_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);

                    return (string) $this->owned($caller, $input->uuid('assignation_id'))['name'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = $input->uuid('assignation_id');
                    $this->commands->dispatch(new UpdateAssignation($caller, $id, $input->only(array_values(array_diff(self::FIELDS, ['type'])))));

                    return ['assignation_id' => $id];
                },
            ),
            ChatTool::write(
                'delete_assignation',
                'Deletes an assignation; the answers already given are kept.',
                ['assignation_id' => Schema::id('assignation')],
                ['assignation_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);

                    return (string) $this->owned($caller, $input->uuid('assignation_id'))['name'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = $input->uuid('assignation_id');
                    $this->commands->dispatch(new DeleteAssignation($caller, $id));

                    return ['assignation_id' => $id, 'deleted' => true];
                },
            ),
            ChatTool::write(
                'send_follow_up_reminder',
                'Emails the respondents of a follow-up the link to answer, and the account\'s owners its status.',
                ['assignation_id' => Schema::id('assignation')],
                ['assignation_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);

                    return (string) $this->owned($caller, $input->uuid('assignation_id'))['name'];
                },
                function (Caller $caller, ToolInput $input): array {
                    /** @var ReminderOutcome $outcome */
                    $outcome = $this->commands->dispatch(new SendReminder($input->uuid('assignation_id'), $caller));

                    return ['recipients' => $outcome->recipients];
                },
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function list(Caller $caller, ToolInput $input): array
    {
        $size = $input->pageSize();
        $offset = intdiv($input->offset(), $size) * $size;
        $type = null === ($input->all()['type'] ?? null) ? null : $input->choice('type', ['default', 'follow_up']);
        $page = $this->details->page($caller->isAdmin() ? null : $caller->customerId, $type, $input->optionalUuid('questionnaire_id'), intdiv($offset, $size) + 1, $size);

        return Schema::page(array_map(self::summary(...), $page['items']), $offset, $page['total']);
    }

    /** @return array<string, mixed> */
    private function respondents(Caller $caller, ToolInput $input): array
    {
        $offset = $input->offset();
        $page = $this->details->respondentsOf($input->uuid('assignation_id'), $offset, $input->pageSize());
        if (null === $page || !$caller->owns($page['customer_id'])) {
            throw self::notFound();
        }

        return [
            'rows' => array_map(static fn (array $r): array => [
                'name' => $r['organization_user_name'],
                'email' => $r['organization_user_email'],
                'status' => $r['status'],
                'attempts' => $r['attempts'],
            ], $page['respondents']),
            'offset' => $offset,
            'has_more' => null !== $page['next_offset'],
        ];
    }

    /** @return array<string, mixed> */
    private function owned(Caller $caller, string $id): array
    {
        $assignation = $this->details->find($id, true);
        if (null === $assignation || !$caller->owns((string) $assignation['customer_id'])) {
            throw self::notFound();
        }

        return $assignation;
    }

    /**
     * @param array<string, mixed> $a
     *
     * @return array<string, mixed>
     */
    private static function summary(array $a): array
    {
        return [
            'assignation_id' => $a['assignations_id'],
            'name' => $a['name'],
            'type' => $a['type'],
            'active' => $a['active'],
            'organization_id' => $a['organization_id'],
            'organization_name' => $a['organization_name'] ?? null,
            'questionnaire_id' => $a['questionnaire_id'],
            'questionnaire_name' => $a['questionnaire_name'] ?? null,
            'url' => $a['questionnaire_url'] ?? null,
            'due_date' => $a['due_date'],
            'audience' => $a['audience'],
            'audience_size' => $a['audience_size'] ?? null,
            'progress' => $a['progress'] ?? null,
            'completed' => $a['completed'] ?? null,
            'project_id' => $a['project_id'],
        ];
    }

    /**
     * The default registration slide (PRD §10.11): full name and email, both required, named after the field (the
     * respondent page recognizes them by name or type).
     *
     * @return array<string, mixed>
     */
    private function registration(Caller $caller): array
    {
        $language = str_starts_with((string) ($this->accounts->find($caller->customerId)['language'] ?? 'es'), 'en') ? 'en' : 'es';

        return [
            'id' => 'registration-1',
            'title' => self::REGISTRATION_TITLE[$language],
            'category' => 'user-capture-data',
            'required' => true,
            'options' => [
                ['name' => 'name', 'type' => 'text', 'options' => [], 'validations' => [['type' => 'required']]],
                ['name' => 'email', 'type' => 'email', 'options' => [], 'validations' => [['type' => 'required']]],
            ],
        ];
    }

    private static function notFound(): NotFound
    {
        return new NotFound('ASSIGNATION_NOT_FOUND', 'Assignation not found.');
    }
}
