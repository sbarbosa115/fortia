<?php

namespace App\Reporting\Application\Query;

use App\Questionnaires\Application\Query\QuestionnaireView;
use App\Reporting\Application\Port\SessionReadModel;
use App\Reporting\Domain\Error\AnalyticsUnavailable;
use App\Reporting\Domain\Model\AnswerStatistics;
use App\Reporting\Domain\Repository\DashboardRepository;
use App\Shared\Domain\Iso;

/** The stored dashboard of a questionnaire and its data (PRD §10.9). */
final class DashboardQueries
{
    public function __construct(
        private readonly DashboardRepository $dashboards,
        private readonly SessionReadModel $sessions,
    ) {
    }

    /** @return array{questionnaire_id: string, customer_id: string, type: string, charts: list<array<string, mixed>>, created_at: string|null}|null */
    public function stored(string $questionnaireId): ?array
    {
        $dashboard = $this->dashboards->find($questionnaireId);

        return null === $dashboard ? null : [
            'questionnaire_id' => $dashboard->questionnaireId(),
            'customer_id' => $dashboard->customerId(),
            'type' => $dashboard->type(),
            'charts' => $dashboard->charts(),
            'created_at' => Iso::datetime($dashboard->createdAt()),
        ];
    }

    /**
     * The §10.9 data, computed from the questionnaire's stored sessions.
     *
     * @return array{sessions: array<string, mixed>, questions: list<array<string, mixed>>, tiers: list<array{tier_id: string, name: string, count: int}>}
     */
    public function data(QuestionnaireView $questionnaire): array
    {
        try {
            return AnswerStatistics::compute(
                $this->sessions->all($questionnaire->id()),
                ReportedQuestionnaires::profiles($questionnaire),
                ReportedQuestionnaires::isDiagnostic($questionnaire) ? $this->sessions->diagnostics($questionnaire->id()) : [],
            );
        } catch (\Throwable $failure) {
            throw new AnalyticsUnavailable($failure);
        }
    }
}
