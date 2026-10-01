<?php

namespace App\Assignations\Infrastructure\Seed;

use App\Assignations\Domain\Audience;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Questionnaires\Application\Command\SaveFlow;
use App\Responses\Application\Command\RecordReview;
use App\Responses\Application\Command\SaveSession;
use App\Responses\Application\Command\StartSession;
use App\Responses\Application\Command\SubmitSession;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\RespondentClaims;
use App\Shared\Application\Seed\DemoAccounts;
use App\Shared\Application\Seed\DemoSeeder;
use App\Shared\Domain\Clock;

/**
 * Demo assignations for the regression suite (docs/tests/ui-regression.md ASG), all of Acme Retail (the organizations
 * seeder's), each on a questionnaire of its own:
 *
 * - "Customer service survey" (default, everybody): María and Juan answered, Lucía started, Pedro has not;
 * - "Monthly store report" (follow-up, area Sales, due in 5 days): nobody opened it yet — the reminder's case;
 * - "Inventory count" (follow-up, everybody): complete and waiting for review;
 * - "Safety audit" (follow-up, everybody, due 3 days ago): complete, one answer approved and one rejected — ready to
 *   "Send for correction".
 *
 * Runs after the questionnaires (40) and organizations (50) seeders and before the projects one (30), which seeds
 * its own follow-ups. Fixed assignation ids make it run once; without Acme Retail it does nothing.
 */
final class DemoAssignationsSeeder implements DemoSeeder
{
    public const SURVEY = '5d2e7a90-3c1b-4f6e-9a8d-000000000301';
    public const STORE_REPORT = '5d2e7a90-3c1b-4f6e-9a8d-000000000302';
    public const INVENTORY = '5d2e7a90-3c1b-4f6e-9a8d-000000000303';
    public const SAFETY = '5d2e7a90-3c1b-4f6e-9a8d-000000000304';
    /** DemoOrganizationsSeeder::ACME_RETAIL, named here so this context does not import that seeder. */
    private const ACME_RETAIL = '0a9f3c1e-5b7d-4e2a-9c10-000000000001';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly OrganizationQueries $organizations,
        private readonly SessionQueries $sessions,
        private readonly AssignationRepository $assignations,
        private readonly Clock $clock,
    ) {
    }

    public static function priority(): int
    {
        return 35;
    }

    public function seed(): void
    {
        if (null !== $this->assignations->find(self::SURVEY) || null === $this->organizations->find(self::ACME_RETAIL)) {
            return;
        }
        $members = [];
        foreach ($this->organizations->membersOf(self::ACME_RETAIL) as $member) {
            $members[strtolower((string) ($member['email'] ?? $member['phone']))] = (string) $member['organization_user_id'];
        }

        $survey = $this->assignation(self::SURVEY, Assignation::DEFAULT, 'Customer service survey', 'acme-customer-service-survey', 'Every store, once a quarter.', null, Audience::everybody());
        foreach (['maria@acme-retail.test' => true, 'juan@acme-retail.test' => true, 'lucia@acme-retail.test' => false] as $email => $submit) {
            if (isset($members[$email])) {
                $this->answerAsMember($survey, $members[$email], $submit);
            }
        }

        $this->assignation(self::STORE_REPORT, Assignation::FOLLOW_UP, 'Monthly store report', 'acme-monthly-store-report', null, $this->clock->now()->modify('+5 days')->format('Y-m-d'), ['type' => Audience::AREA, 'values' => ['Sales']]);

        $inventory = $this->assignation(self::INVENTORY, Assignation::FOLLOW_UP, 'Inventory count', 'acme-inventory-count', 'Count the back room too.', null, Audience::everybody());
        $this->answerShared($inventory);

        $safety = $this->assignation(self::SAFETY, Assignation::FOLLOW_UP, 'Safety audit', 'acme-safety-audit', null, $this->clock->now()->modify('-3 days')->format('Y-m-d'), Audience::everybody());
        $sessionId = $this->answerShared($safety);
        $questions = $this->sessions->find($sessionId)?->questions() ?? [];
        foreach ([0 => 'approved', 1 => 'rejected'] as $i => $status) {
            if (isset($questions[$i]['id'])) {
                $comment = 'rejected' === $status ? 'Add a photo of each fire extinguisher.' : null;
                $this->commands->dispatch(new RecordReview($sessionId, (string) $questions[$i]['id'], $status, $comment, 1));
            }
        }
        foreach (\array_slice($questions, 2) as $question) {
            $this->commands->dispatch(new RecordReview($sessionId, (string) $question['id'], 'approved', null, 1));
        }
    }

    /** @param array{type: string, values: list<string>} $audience */
    private function assignation(string $id, string $type, string $title, string $slug, ?string $description, ?string $dueDate, array $audience): Assignation
    {
        $questionnaireId = (string) $this->commands->dispatch(new SaveFlow(DemoAccounts::ACME, self::states($title), $slug, source: 'seed'));
        $now = $this->clock->now();
        $assignation = new Assignation($id, DemoAccounts::ACME, self::ACME_RETAIL, $questionnaireId, $title, $type, $now);
        $assignation->configure(self::ACME_RETAIL, $questionnaireId, $title, $description, 2, true, $dueDate, $audience, [self::registration()], $now);
        $this->assignations->add($assignation);

        return $assignation;
    }

    private function answerAsMember(Assignation $assignation, string $memberId, bool $submit): void
    {
        $sessionId = (string) $this->commands->dispatch(new StartSession($assignation->questionnaireId(), $assignation->assignationsId(), $memberId, null, 1));
        $this->fill($assignation, $sessionId, $memberId, $submit ? 99 : 1, $submit);
    }

    /** Opens the follow-up's shared session (attempt 1) and submits it with every question answered. */
    private function answerShared(Assignation $assignation): string
    {
        $sessionId = (string) $this->commands->dispatch(new StartSession($assignation->questionnaireId(), $assignation->assignationsId(), null, Assignation::FOLLOW_UP, 1));
        $assignation->startAttempt($sessionId, $this->clock->now());
        $this->fill($assignation, $sessionId, 'demo-seed', 99, true);

        return $sessionId;
    }

    private function fill(Assignation $assignation, string $sessionId, string $memberId, int $answered, bool $submit): void
    {
        $questions = $this->sessions->find($sessionId)?->questions() ?? [];
        $answers = ['All good, see the photos in the shared folder.', 'Two shelves need repairs before Friday.', 'Nothing blocked right now.', 'More boxes for the weekend.'];
        foreach ($questions as $i => $question) {
            if ($i < $answered) {
                $questions[$i]['options'][0]['value'] = $answers[$i % \count($answers)];
                $questions[$i]['options'][0]['timestamp'] = $this->clock->now()->format('Y-m-d\TH:i:s\Z');
            }
        }
        $claims = new RespondentClaims($assignation->assignationsId(), $memberId, $sessionId);
        $this->commands->dispatch($submit ? new SubmitSession($sessionId, $questions, null, $claims) : new SaveSession($sessionId, $questions, $claims));
    }

    /** @return list<array<string, mixed>> */
    private static function states(string $title): array
    {
        $questions = ['What went well this period?', 'What needs to be fixed, and by when?', 'Which risks do you see?', 'What do you need from us?'];

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
            'id' => 'registration-1',
            'title' => 'Sign in',
            'category' => 'user-capture-data',
            'options' => [
                ['name' => 'name', 'type' => 'text', 'validations' => [['type' => 'required']]],
                ['name' => 'email', 'type' => 'email', 'validations' => [['type' => 'required']]],
            ],
        ];
    }
}
