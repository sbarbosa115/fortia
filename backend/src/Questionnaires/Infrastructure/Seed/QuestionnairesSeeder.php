<?php

namespace App\Questionnaires\Infrastructure\Seed;

use App\Questionnaires\Application\Command\SaveFlow;
use App\Questionnaires\Application\Command\SetQuestionnaireActive;
use App\Questionnaires\Domain\Repository\FlowRepository;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Seed\DemoAccounts;
use App\Shared\Application\Seed\DemoSeeder;

/**
 * Demo questionnaires for the listing (docs/tests/ui-regression.md QST): Acme has twelve of every kind (one inactive),
 * enough for two pages at 10 per page; Globex has one, which Acme must never see. Seeded through SaveFlow, like the
 * console, without counting usage. Skips a questionnaire whose slug already exists.
 */
final class QuestionnairesSeeder implements DemoSeeder
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly FlowRepository $flows,
    ) {
    }

    public static function priority(): int
    {
        return 40;
    }

    public function seed(): void
    {
        $this->regular(DemoAccounts::ACME, 'acme-satisfaction', 'Customer satisfaction survey', ['How satisfied are you with our service?', 'Would you recommend us to a friend?']);
        $this->regular(DemoAccounts::ACME, 'acme-clima-laboral', 'Encuesta de clima laboral', ['¿Cómo calificas el ambiente de trabajo?']);
        $this->save(DemoAccounts::ACME, 'acme-ai-maturity', self::diagnosticStates());
        $this->save(DemoAccounts::ACME, 'acme-discovery-chain', self::chainStates());
        $this->save(DemoAccounts::ACME, 'acme-operations-map', self::states('Process map: operations', ['Which steps does an order go through?'], ['type' => 'process_mapping', 'message' => 'Thanks for mapping your process.']));
        for ($week = 1; $week <= 6; ++$week) {
            $this->regular(DemoAccounts::ACME, "acme-pulse-week-$week", "Team pulse, week $week", ['How was your week?']);
        }
        $onboarding = $this->regular(DemoAccounts::ACME, 'acme-onboarding-feedback', 'Onboarding feedback', ['How clear was your first day?']);
        if (null !== $onboarding) {
            $this->commands->dispatch(new SetQuestionnaireActive($onboarding, false));
        }

        $this->regular(DemoAccounts::GLOBEX, 'globex-product-feedback', 'Globex product feedback', ['What should we build next?']);
    }

    /** @param list<string> $questions */
    private function regular(string $customerId, string $slug, string $title, array $questions): ?string
    {
        return $this->save($customerId, $slug, self::states($title, $questions));
    }

    /** @param list<array<string, mixed>> $states */
    private function save(string $customerId, string $slug, array $states): ?string
    {
        if (null !== $this->flows->findBySlug($slug)) {
            return null;
        }

        return (string) $this->commands->dispatch(new SaveFlow($customerId, $states, $slug, source: 'seed', countsUsage: false));
    }

    /**
     * @param list<string>              $questions
     * @param array<string, mixed>|null $onCompleted
     *
     * @return list<array<string, mixed>>
     */
    private static function states(string $title, array $questions, ?array $onCompleted = null): array
    {
        return [[
            'state_id' => 'start',
            'type' => 'questionnaire',
            'parameters' => ['questionnaire' => [
                'title' => $title,
                'landing_page' => true,
                'on_completed' => $onCompleted,
                'questions' => array_map(static fn (string $question): array => [
                    'title' => $question,
                    'options' => [['type' => 'radio', 'options' => [
                        ['label' => 'Very good', 'value' => 'very-good'],
                        ['label' => 'Good', 'value' => 'good'],
                        ['label' => 'Bad', 'value' => 'bad'],
                    ]]],
                ], $questions),
            ]],
        ]];
    }

    /** @return list<array<string, mixed>> */
    private static function diagnosticStates(): array
    {
        $states = self::states('AI maturity diagnostic', [], [
            'type' => 'diagnostic',
            'tiers' => [
                ['id' => 'explorer', 'name' => 'Explorer', 'min' => 0, 'max' => 4],
                ['id' => 'practitioner', 'name' => 'Practitioner', 'min' => 5, 'max' => 9],
                ['id' => 'leader', 'name' => 'Leader', 'min' => 10, 'max' => 14],
            ],
            'recommendations' => [
                ['tier_id' => 'explorer', 'recommendation' => 'Start with one internal use case.'],
                ['tier_id' => 'practitioner', 'recommendation' => 'Measure the impact of what you run.'],
                ['tier_id' => 'leader', 'recommendation' => 'Share your playbook across teams.'],
            ],
            'action_plan' => [['tier_id' => 'explorer', 'action' => 'Pick a pilot this month.']],
        ]);
        $states[0]['next'] = 'diag';
        $states[0]['parameters']['questionnaire']['questions'] = [
            ['title' => 'How often does your team use AI tools?', 'category' => 'Usage', 'options' => [['type' => 'radio', 'options' => [
                ['label' => 'Never', 'value' => 0], ['label' => 'Sometimes', 'value' => 4], ['label' => 'Daily', 'value' => 7],
            ]]]],
            ['title' => 'Is there an AI policy?', 'category' => 'Governance', 'options' => [['type' => 'radio', 'options' => [
                ['label' => 'No', 'value' => 0], ['label' => 'In progress', 'value' => 3], ['label' => 'Yes', 'value' => 7],
            ]]]],
        ];
        $states[] = ['state_id' => 'diag', 'type' => 'diagnostic'];

        return $states;
    }

    /** @return list<array<string, mixed>> */
    private static function chainStates(): array
    {
        $states = self::states('Discovery chain', ['What do you want to improve this quarter?']);
        $states[0]['next'] = 'p1';
        $states[] = ['state_id' => 'p1', 'type' => 'prompt', 'next' => 'end', 'parameters' => ['text' => 'Ask three follow-up questions about the goal they chose, one per area: people, process and tools.']];
        $states[] = ['state_id' => 'end', 'type' => 'result'];

        return $states;
    }
}
