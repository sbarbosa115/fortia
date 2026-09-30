<?php

declare(strict_types=1);

namespace App\Assignations\Application\Query;

use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Domain\Iso;

/**
 * Reads of the Assignations context for other contexts (sessions need to know whether a questionnaire is assigned,
 * and how a follow-up's shared session merges; flows are hidden by slug when assigned, PRD §8.4).
 */
final class AssignationQueries
{
    public function __construct(private readonly AssignationRepository $assignations)
    {
    }

    /** A questionnaire assigned to an organization is only answered through /a/{id} (PRD §8.4). */
    public function isQuestionnaireAssigned(string $questionnaireId): bool
    {
        return null !== $this->assignations->findByQuestionnaire($questionnaireId);
    }

    /** @return array<string, mixed>|null */
    public function find(string $assignationsId): ?array
    {
        $assignation = $this->assignations->find($assignationsId);

        return null === $assignation ? null : self::assignationData($assignation);
    }

    /** @return array<string, mixed>|null */
    public function findByQuestionnaire(string $questionnaireId): ?array
    {
        $assignation = $this->assignations->findByQuestionnaire($questionnaireId);

        return null === $assignation ? null : self::assignationData($assignation);
    }

    /** @return array<string, mixed> the PRD §6.14 shape */
    public static function assignationData(Assignation $a): array
    {
        return [
            'assignations_id' => $a->assignationsId(),
            'customer_id' => $a->customerId(),
            'organization_id' => $a->organizationId(),
            'questionnaire_id' => $a->questionnaireId(),
            'name' => $a->name(),
            'description' => $a->description(),
            'max_follow_ups' => $a->maxFollowUps(),
            'active' => $a->isActive(),
            'type' => $a->type(),
            'due_date' => $a->dueDate(),
            'audience' => $a->audience(),
            'questions' => $a->questions(),
            'project_id' => $a->projectId(),
            'shared_session_id' => $a->sharedSessionId(),
            'attempts' => $a->attempts(),
            'last_reminder_sent_at' => Iso::datetime($a->lastReminderSentAt()),
            'created_at' => Iso::datetime($a->createdAt()),
            'updated_at' => Iso::datetime($a->updatedAt()),
        ];
    }
}
