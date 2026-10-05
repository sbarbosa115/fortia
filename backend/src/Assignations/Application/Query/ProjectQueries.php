<?php

namespace App\Assignations\Application\Query;

use App\Assignations\Domain\FollowUpProgress;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Model\Project;
use App\Assignations\Domain\ProjectStatus;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Iso;

/**
 * Reads of projects, enriched as PRD §7.12 says: the project's fields, its organization's name, its state,
 * progress_percent, completed/approved/total_assignations, and each assignation with its state, progress
 * ("Question 4 of 8"), review counts and due date.
 *
 * An assignation is due on its own due_date or, without one, on the project's.
 */
final class ProjectQueries
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly AssignationRepository $assignations,
        private readonly FollowUpStatus $followUps,
        private readonly OrganizationQueries $organizations,
        private readonly Clock $clock,
    ) {
    }

    /**
     * The enriched project with `available_assignations`: the organization's follow-ups that are not in another
     * project (the edit dialog's choices, PRD §10.12). Null when it does not exist; the caller checks ownership.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $projectId): ?array
    {
        $project = $this->projects->find($projectId);
        if (null === $project) {
            return null;
        }
        $names = [];
        $data = $this->enrich($project, $this->assignations->listByProject($projectId), $this->today(), $names);
        $available = array_filter(
            $this->assignations->followUpsOfOrganization($project->organizationId()),
            static fn (Assignation $a): bool => null === $a->projectId() || $a->projectId() === $projectId,
        );
        $data['available_assignations'] = array_values(array_map(static fn (Assignation $a): array => [
            'assignations_id' => $a->assignationsId(),
            'name' => $a->name(),
            'project_id' => $a->projectId(),
        ], $available));

        return $data;
    }

    /**
     * One page of GET /projects, newest first. Without a status filter the page is cut in SQL; the state is
     * computed from the sessions, so with one every matching project is evaluated and the page cut afterwards.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function page(ProjectListCriteria $criteria): array
    {
        $today = $this->today();
        $names = [];
        if (null === $criteria->status) {
            $projects = $this->projects->search($criteria->customerId, $criteria->searchWords, $criteria->offset(), $criteria->pageSize);

            return [
                'items' => $this->enrichAll($projects, $today, $names),
                'total' => $this->projects->countSearch($criteria->customerId, $criteria->searchWords),
            ];
        }
        $matching = array_values(array_filter(
            $this->enrichAll($this->projects->search($criteria->customerId, $criteria->searchWords), $today, $names),
            static fn (array $p): bool => ProjectStatus::matchesFilter((string) $p['state'], (string) $criteria->status),
        ));

        return ['items' => \array_slice($matching, $criteria->offset(), $criteria->pageSize), 'total' => \count($matching)];
    }

    /**
     * @param list<Project>         $projects
     * @param array<string, string> $names    organization names already read
     *
     * @return list<array<string, mixed>>
     */
    private function enrichAll(array $projects, string $today, array &$names): array
    {
        $byProject = $this->assignations->listByProjects(array_map(static fn (Project $p): string => $p->projectId(), $projects));
        $out = [];
        foreach ($projects as $project) {
            $out[] = $this->enrich($project, $byProject[$project->projectId()] ?? [], $today, $names);
        }

        return $out;
    }

    /**
     * @param list<Assignation>     $assignations
     * @param array<string, string> $names
     *
     * @return array<string, mixed>
     */
    private function enrich(Project $project, array $assignations, string $today, array &$names): array
    {
        $rows = [];
        $progress = [];
        $states = [];
        $completed = 0;
        $done = 0;
        foreach ($assignations as $assignation) {
            $p = $this->followUps->of($assignation);
            $due = $assignation->dueDate() ?? $project->dueDate();
            $state = ProjectStatus::ofAssignation($p, \count($assignation->attempts()), $due, $today);
            $progress[] = $p;
            $states[] = $state;
            $completed += $p->ended ? 1 : 0;
            // Done: approved when it is reviewed, otherwise complete.
            $done += ($p->requiresReview ? ProjectStatus::APPROVED === $state : $p->ended) ? 1 : 0;
            $rows[] = self::assignationRow($assignation, $p, $state, $due, $today);
        }

        return [
            'project_id' => $project->projectId(),
            'customer_id' => $project->customerId(),
            'organization_id' => $project->organizationId(),
            'organization_name' => $this->organizationName($project->organizationId(), $names),
            'name' => $project->name(),
            'description' => $project->description(),
            'due_date' => $project->dueDate(),
            'requires_review' => [] === $assignations ? $project->requiresReview() : [] !== array_filter($progress, static fn (FollowUpProgress $p): bool => $p->requiresReview),
            'created_at' => Iso::datetime($project->createdAt()),
            'updated_at' => Iso::datetime($project->updatedAt()),
            'state' => ProjectStatus::ofProject($states),
            'progress_percent' => ProjectStatus::progressPercent($progress),
            'completed_assignations' => $completed,
            'approved_assignations' => \count(array_filter($states, static fn (string $s): bool => ProjectStatus::APPROVED === $s)),
            'done_assignations' => $done,
            'total_assignations' => \count($assignations),
            'assignations' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private static function assignationRow(Assignation $a, FollowUpProgress $p, string $state, ?string $due, string $today): array
    {
        return [
            'assignations_id' => $a->assignationsId(),
            'name' => $a->name(),
            'questionnaire_id' => $a->questionnaireId(),
            'active' => $a->isActive(),
            'state' => $state,
            'completed' => $p->ended,
            'review_status' => $p->reviewStatus,
            'requires_review' => $p->requiresReview,
            'attempt' => $a->currentAttempt(),
            'due_date' => $due,
            'overdue' => !$p->ended && ProjectStatus::isOverdue($due, $today),
            'percent' => (int) round($p->percent()),
            'progress' => ['completed' => $p->completed, 'total' => $p->total, 'unit' => 'questions', 'current_question' => $p->currentQuestion],
            'review' => ['reviewed' => $p->reviewed, 'total' => $p->total, 'approved' => $p->approved, 'rejected' => $p->rejected],
        ];
    }

    /** @param array<string, string> $names */
    private function organizationName(string $organizationId, array &$names): string
    {
        if (!isset($names[$organizationId])) {
            $names[$organizationId] = (string) ($this->organizations->find($organizationId)['name'] ?? '');
        }

        return $names[$organizationId];
    }

    private function today(): string
    {
        return ProjectStatus::todayForOverdue($this->clock->now());
    }
}
