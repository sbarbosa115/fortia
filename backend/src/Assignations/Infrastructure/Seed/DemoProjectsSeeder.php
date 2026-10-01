<?php

namespace App\Assignations\Infrastructure\Seed;

use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Model\Project;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Organizations\Application\Command\SaveOrganization;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Questionnaires\Application\Command\SaveFlow;
use App\Responses\Application\Command\RecordReview;
use App\Responses\Application\Command\SaveSession;
use App\Responses\Application\Command\StartSession;
use App\Responses\Application\Command\SubmitSession;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Application\Security\RespondentClaims;
use App\Shared\Application\Seed\DemoAccounts;
use App\Shared\Application\Seed\DemoSeeder;
use App\Shared\Domain\Clock;

/**
 * Demo projects for the regression suite (docs/tests/ui-regression.md PRJ): Acme gets four follow-up assignations
 * (each on a questionnaire of its own) of one organization and two projects:
 *
 * - "Store opening Q4" (due in 30 days): one follow-up in progress (2 of 4 answered) and one complete with 1 of 4
 *   answers reviewed → state "review";
 * - "Supplier audit" (due 5 days ago): one follow-up nobody opened → state "overdue";
 * - one more follow-up in no project, offered by the edit dialog.
 *
 * It uses the organizations seeder's Acme Retail, or any organization of Acme, or creates a small one. Runs after the
 * questionnaires and organizations seeders; fixed assignation and project ids make it run once.
 */
final class DemoProjectsSeeder implements DemoSeeder
{
    public const STORE_OPENING = '7c1d2e3f-4a5b-4c6d-8e7f-000000000101';
    public const SUPPLIER_AUDIT = '7c1d2e3f-4a5b-4c6d-8e7f-000000000102';
    public const IN_PROGRESS = '7c1d2e3f-4a5b-4c6d-8e7f-000000000201';
    public const IN_REVIEW = '7c1d2e3f-4a5b-4c6d-8e7f-000000000202';
    public const NOT_STARTED = '7c1d2e3f-4a5b-4c6d-8e7f-000000000203';
    public const UNASSIGNED = '7c1d2e3f-4a5b-4c6d-8e7f-000000000204';
    /** DemoOrganizationsSeeder::ACME_RETAIL, named here so this context does not import that seeder. */
    private const ACME_RETAIL = '0a9f3c1e-5b7d-4e2a-9c10-000000000001';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly OrganizationQueries $organizations,
        private readonly SessionQueries $sessions,
        private readonly AssignationRepository $assignations,
        private readonly ProjectRepository $projects,
        private readonly Clock $clock,
    ) {
    }

    public static function priority(): int
    {
        return 30;
    }

    public function seed(): void
    {
        if (null !== $this->projects->find(self::STORE_OPENING)) {
            return;
        }
        $organizationId = $this->organization();
        $now = $this->clock->now();

        $inProgress = $this->followUp(self::IN_PROGRESS, $organizationId, 'Store opening checklist', 'acme-store-opening-checklist', null);
        $inReview = $this->followUp(self::IN_REVIEW, $organizationId, 'Visual merchandising review', 'acme-visual-merchandising', null);
        $notStarted = $this->followUp(self::NOT_STARTED, $organizationId, 'Supplier compliance', 'acme-supplier-compliance', null);
        $this->followUp(self::UNASSIGNED, $organizationId, 'Staff training plan', 'acme-staff-training', null);

        $this->answer($inProgress, 2, submit: false);
        $sessionId = $this->answer($inReview, 4, submit: true);
        $first = $this->sessions->find($sessionId)?->questions()[0]['id'] ?? null;
        if (null !== $first) {
            $this->commands->dispatch(new RecordReview($sessionId, (string) $first, 'approved', null, 1));
        }

        $this->project(self::STORE_OPENING, $organizationId, 'Store opening Q4', 'Every new store ready before the holidays.', $now->modify('+30 days')->format('Y-m-d'), [$inProgress, $inReview]);
        $this->project(self::SUPPLIER_AUDIT, $organizationId, 'Supplier audit', null, $now->modify('-5 days')->format('Y-m-d'), [$notStarted]);
    }

    private function organization(): string
    {
        if (null !== $this->organizations->find(self::ACME_RETAIL)) {
            return self::ACME_RETAIL;
        }
        $existing = $this->organizations->listFor(DemoAccounts::ACME)[0]['organization_id'] ?? null;
        if (\is_string($existing)) {
            return $existing;
        }
        $owner = new Caller('seed', DemoAccounts::ACME_OWNER, 'Demo seed', DemoAccounts::ACME, [Caller::CUSTOMER_ADMIN], true);

        return (string) $this->commands->dispatch(SaveOrganization::create($owner, [
            'name' => 'Acme Stores',
            'organization_users' => [['name' => 'Marta Ríos', 'email' => 'marta@acme-stores.test', 'role' => 'Store manager', 'area' => 'Sales']],
        ]));
    }

    private function followUp(string $id, string $organizationId, string $title, string $slug, ?string $dueDate): Assignation
    {
        $questionnaireId = (string) $this->commands->dispatch(new SaveFlow(DemoAccounts::ACME, self::states($title), $slug, source: 'seed'));
        $now = $this->clock->now();
        $assignation = new Assignation($id, DemoAccounts::ACME, $organizationId, $questionnaireId, $title, Assignation::FOLLOW_UP, $now);
        $assignation->configure($organizationId, $questionnaireId, $title, null, 2, true, $dueDate, ['type' => 'all', 'values' => []], [self::registration()], $now);
        $this->assignations->add($assignation);

        return $assignation;
    }

    /** Opens the shared session and answers its first $answered questions (submitting it when $submit). */
    private function answer(Assignation $assignation, int $answered, bool $submit): string
    {
        $sessionId = (string) $this->commands->dispatch(new StartSession($assignation->questionnaireId(), $assignation->assignationsId(), null, Assignation::FOLLOW_UP, 1));
        $assignation->startAttempt($sessionId, $this->clock->now());
        $questions = $this->sessions->find($sessionId)?->questions() ?? [];
        foreach ($questions as $i => $question) {
            if ($i < $answered) {
                $questions[$i]['options'][0]['value'] = 'Done: see the photos in the shared folder.';
                $questions[$i]['options'][0]['timestamp'] = $this->clock->now()->format('Y-m-d\TH:i:s\Z');
            }
        }
        $claims = new RespondentClaims($assignation->assignationsId(), 'demo-seed', $sessionId);
        $this->commands->dispatch($submit ? new SubmitSession($sessionId, $questions, null, $claims) : new SaveSession($sessionId, $questions, $claims));

        return $sessionId;
    }

    /** @param list<Assignation> $assignations */
    private function project(string $id, string $organizationId, string $name, ?string $description, string $dueDate, array $assignations): void
    {
        $now = $this->clock->now();
        $project = new Project($id, DemoAccounts::ACME, $organizationId, $name, $dueDate, $now);
        $project->change($name, $description, $dueDate, $now);
        $this->projects->add($project);
        foreach ($assignations as $assignation) {
            $assignation->joinProject($id, $now);
        }
    }

    /** @return list<array<string, mixed>> */
    private static function states(string $title): array
    {
        $questions = ['What is already done?', 'What is blocked, and by whom?', 'Which risks do you see?', 'What do you need from us?'];

        return [[
            'state_id' => 'start',
            'type' => 'questionnaire',
            'parameters' => ['questionnaire' => [
                'title' => $title,
                'landing_page' => false,
                'questions' => array_map(static fn (string $q): array => [
                    'title' => $q,
                    'options' => [['type' => 'text', 'options' => [], 'validations' => []]],
                ], $questions),
            ]],
        ]];
    }

    /** @return array<string, mixed> the registration slide (respondent login, PRD §10.11 defaults) */
    private static function registration(): array
    {
        return [
            'id' => 'registration',
            'title' => 'Who is answering?',
            'options' => [
                ['name' => 'name', 'type' => 'text', 'validations' => [['type' => 'required']]],
                ['name' => 'email', 'type' => 'email', 'validations' => [['type' => 'required']]],
            ],
            'category' => 'user-capture-data',
        ];
    }
}
