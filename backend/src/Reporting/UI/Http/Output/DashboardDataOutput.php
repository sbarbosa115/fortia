<?php

namespace App\Reporting\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * GET /questionnaire/{id}/dashboard/data (PRD §10.9): the sessions summary and each question's answers, computed
 * from the stored sessions; tiers counts the diagnostic tier of each session (the tier_distribution chart).
 */
final class DashboardDataOutput
{
    /**
     * @param list<QuestionStatsOutput> $questions
     * @param list<TierCountOutput>     $tiers
     */
    public function __construct(
        public readonly DashboardSessionsOutput $sessions,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: QuestionStatsOutput::class)))]
        public readonly array $questions,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: TierCountOutput::class)))]
        public readonly array $tiers,
    ) {
    }

    /** @param array{sessions: array<string, mixed>, questions: list<array<string, mixed>>, tiers: list<array{tier_id: string, name: string, count: int}>} $data */
    public static function of(array $data): self
    {
        return new self(
            DashboardSessionsOutput::of($data['sessions']),
            QuestionStatsOutput::list($data['questions']),
            array_map(static fn (array $t): TierCountOutput => new TierCountOutput($t['tier_id'], $t['name'], $t['count']), $data['tiers']),
        );
    }
}
