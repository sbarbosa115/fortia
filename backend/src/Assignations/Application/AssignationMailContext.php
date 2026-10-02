<?php

namespace App\Assignations\Application;

use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Identity\Application\Query\AccountQueries;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Shared\Application\Mail\OutgoingEmail;

/**
 * What the assignation emails need to know (PRD §7.13, §7.21): the organization's members, the account's language
 * (`en` or `es`, `es` by default) and its root users, and the assignation as the templates show it, due on its own
 * date or, without one, on its project's (§7.12).
 */
final class AssignationMailContext
{
    public function __construct(
        private readonly OrganizationQueries $organizations,
        private readonly AccountQueries $accounts,
        private readonly ProjectRepository $projects,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function members(Assignation $assignation): array
    {
        return $this->organizations->membersOf($assignation->organizationId());
    }

    public function locale(Assignation $assignation): string
    {
        $language = $this->accounts->find($assignation->customerId())['language'] ?? null;

        return OutgoingEmail::localeOf(\is_string($language) ? $language : null);
    }

    /** @return list<string> the lowercase emails of the account's root users */
    public function rootEmails(Assignation $assignation): array
    {
        $emails = [];
        foreach ($this->accounts->usersOf($assignation->customerId()) as $user) {
            $email = strtolower($user['email']);
            if ($user['root'] && !\in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /** @return array{assignations_id: string, name: string, organization_name: string, due_date: string|null} */
    public function view(Assignation $assignation): array
    {
        return [
            'assignations_id' => $assignation->assignationsId(),
            'name' => $assignation->name(),
            'organization_name' => (string) ($this->organizations->find($assignation->organizationId())['name'] ?? ''),
            'due_date' => $this->dueDate($assignation),
        ];
    }

    public function dueDate(Assignation $assignation): ?string
    {
        $projectId = $assignation->projectId();
        if (null !== $assignation->dueDate() || null === $projectId) {
            return $assignation->dueDate();
        }

        return $this->projects->find($projectId)?->dueDate();
    }
}
