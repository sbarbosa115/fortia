<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * Creates a project of one organization with its follow-up assignations (PRD §8.9 POST /projects). Returns the
 * project id. The console calls it an "assignation": an organization, N questionnaires and a deadline.
 *
 *     $id = $commands->dispatch(new CreateProject($caller, $organizationId, 'Q4 audits', null, '2026-12-15', [$a1, $a2]));
 *     $id = $commands->dispatch(new CreateProject($caller, $organizationId, 'Q4 audits', null, '2026-12-15', [], [$q1, $q2]));
 *
 * The handler checks the organization (404 ORGANIZATION_NOT_FOUND, also another account's) and each assignation
 * (ProjectAssignationSet). Each of $questionnaireIds becomes a new follow-up of the organization (everybody, the
 * default registration, the project's due date), checked like POST /assignations (404 QUESTIONNAIRE_NOT_FOUND); a
 * questionnaire another organization has is assigned as is. $requiresReview (the project's, copied onto every
 * follow-up in it): complete follow-ups go to review, or are simply completed when false. It does NOT run the write-permission check: the caller does first
 * ($caller->canWrite()).
 */
final class CreateProject
{
    /**
     * @param list<string> $assignationIds   duplicates are ignored
     * @param list<string> $questionnaireIds duplicates are ignored
     */
    public function __construct(
        public readonly Caller $caller,
        public readonly string $organizationId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $dueDate,
        public readonly array $assignationIds = [],
        public readonly array $questionnaireIds = [],
        public readonly ?string $registrationTitle = null,
        public readonly bool $requiresReview = true,
    ) {
    }
}
