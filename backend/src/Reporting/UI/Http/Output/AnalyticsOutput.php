<?php

namespace App\Reporting\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /questionnaire/{id}/analytics (PRD §8.4): the general analytics of a questionnaire. */
final class AnalyticsOutput
{
    /** @param list<QuestionStatsOutput> $questions_analytics */
    public function __construct(
        public readonly string $questionnaire_id,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: QuestionStatsOutput::class)))]
        public readonly array $questions_analytics,
        public readonly int $total_sessions,
        public readonly int $sessions_completed,
    ) {
    }
}
