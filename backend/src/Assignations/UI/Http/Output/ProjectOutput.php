<?php

namespace App\Assignations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * An enriched project (PRD §6.16, §7.12): its fields, its organization's name, its state, progress and counts, and
 * its assignations. `available_assignations` (the edit dialog's choices, PRD §10.12) is only in GET /projects/{id}.
 */
final class ProjectOutput
{
    /**
     * @param list<ProjectAssignationOutput>               $assignations
     * @param list<ProjectAvailableAssignationOutput>|null $available_assignations
     */
    public function __construct(
        public readonly string $project_id,
        public readonly string $customer_id,
        public readonly string $organization_id,
        public readonly string $organization_name,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $due_date,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
        #[OA\Property(enum: ['review', 'overdue', 'correction', 'progress', 'pending', 'completed', 'approved', 'empty'])]
        public readonly string $state,
        public readonly int $progress_percent,
        public readonly int $completed_assignations,
        public readonly int $approved_assignations,
        public readonly int $total_assignations,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ProjectAssignationOutput::class)))]
        public readonly array $assignations,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(ref: new Model(type: ProjectAvailableAssignationOutput::class)))]
        public readonly ?array $available_assignations = null,
        /** Any of its follow-ups goes to review once complete (each one says so in assignations[].requires_review). */
        public readonly bool $requires_review = true,
        /** Its follow-ups that are done: approved when reviewed, complete otherwise. */
        public readonly int $done_assignations = 0,
    ) {
    }

    /** @param array<string, mixed> $data ProjectQueries' project */
    public static function of(array $data): self
    {
        /** @var list<array<string, mixed>> $assignations */
        $assignations = $data['assignations'];
        /** @var list<array<string, mixed>>|null $available */
        $available = $data['available_assignations'] ?? null;

        return new self(
            (string) $data['project_id'],
            (string) $data['customer_id'],
            (string) $data['organization_id'],
            (string) $data['organization_name'],
            (string) $data['name'],
            null === $data['description'] ? null : (string) $data['description'],
            null === $data['due_date'] ? null : (string) $data['due_date'],
            null === $data['created_at'] ? null : (string) $data['created_at'],
            null === $data['updated_at'] ? null : (string) $data['updated_at'],
            (string) $data['state'],
            (int) $data['progress_percent'],
            (int) $data['completed_assignations'],
            (int) $data['approved_assignations'],
            (int) $data['total_assignations'],
            array_map(ProjectAssignationOutput::of(...), $assignations),
            null === $available ? null : array_map(ProjectAvailableAssignationOutput::of(...), $available),
            (bool) ($data['requires_review'] ?? true),
            (int) ($data['done_assignations'] ?? 0),
        );
    }
}
