<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Domain\Model\QuestionnaireDashboard;
use App\Reporting\Domain\Model\QuestionProfile;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * GET /questionnaire/{id}/dashboard (PRD §8.4): the stored layout and the questions its charts use. title is the
 * questionnaire's.
 */
final class DashboardOutput
{
    /**
     * @param list<DashboardChartOutput>    $charts
     * @param list<DashboardQuestionOutput> $questions
     */
    public function __construct(
        public readonly string $questionnaire_id,
        public readonly string $customer_id,
        public readonly string $title,
        #[OA\Property(enum: QuestionnaireDashboard::TYPES, nullable: true)]
        public readonly ?string $type,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: DashboardChartOutput::class)))]
        public readonly array $charts,
        public readonly ?string $created_at,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: DashboardQuestionOutput::class)))]
        public readonly array $questions,
    ) {
    }

    /**
     * @param array{questionnaire_id: string, customer_id: string, type: string, charts: list<array<string, mixed>>, created_at: string|null}|null $dashboard
     * @param list<QuestionProfile>                                                                                                                $questions
     */
    public static function of(string $questionnaireId, string $customerId, string $title, ?array $dashboard, array $questions): self
    {
        return new self(
            $questionnaireId,
            $dashboard['customer_id'] ?? $customerId,
            $title,
            $dashboard['type'] ?? null,
            array_map(static fn (array $c): DashboardChartOutput => new DashboardChartOutput(
                (string) ($c['id'] ?? ''),
                (string) ($c['chart_type'] ?? ''),
                (string) ($c['title'] ?? ''),
                array_values(array_map('strval', (array) ($c['question_ids'] ?? []))),
                (int) ($c['order'] ?? 0),
            ), $dashboard['charts'] ?? []),
            $dashboard['created_at'] ?? null,
            array_map(static fn (QuestionProfile $q): DashboardQuestionOutput => DashboardQuestionOutput::of($q), $questions),
        );
    }
}
