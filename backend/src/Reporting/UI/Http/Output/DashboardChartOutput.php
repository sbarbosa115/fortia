<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Domain\Model\QuestionnaireDashboard;
use OpenApi\Attributes as OA;

/** One chart of a dashboard (PRD §6.19). */
final class DashboardChartOutput
{
    /** @param list<string> $question_ids */
    public function __construct(
        public readonly string $id,
        #[OA\Property(enum: QuestionnaireDashboard::CHART_TYPES)]
        public readonly string $chart_type,
        public readonly string $title,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
        public readonly array $question_ids,
        public readonly int $order,
    ) {
    }
}
