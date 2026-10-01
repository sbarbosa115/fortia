<?php

namespace App\Reporting\UI\Http\Output;

use OpenApi\Attributes as OA;

/** The sessions summary of PRD §10.9. by_source: link (a public link) or assignation. */
final class DashboardSessionsOutput
{
    /**
     * @param list<array{date: string, started: int, completed: int}> $timeline
     * @param list<array{source: string, count: int}>                 $by_source
     * @param array{avg: float|null, median: float|null}              $duration_seconds
     */
    public function __construct(
        public readonly int $total,
        public readonly int $completed,
        #[OA\Property(minimum: 0, maximum: 1)]
        public readonly float $completion_rate,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object', required: ['date', 'started', 'completed'], properties: [
            new OA\Property(property: 'date', type: 'string', format: 'date'),
            new OA\Property(property: 'started', type: 'integer'),
            new OA\Property(property: 'completed', type: 'integer'),
        ]))]
        public readonly array $timeline,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object', required: ['source', 'count'], properties: [
            new OA\Property(property: 'source', type: 'string', enum: ['link', 'assignation']),
            new OA\Property(property: 'count', type: 'integer'),
        ]))]
        public readonly array $by_source,
        #[OA\Property(type: 'object', required: ['avg', 'median'], properties: [
            new OA\Property(property: 'avg', type: 'number', nullable: true),
            new OA\Property(property: 'median', type: 'number', nullable: true),
        ])]
        public readonly array $duration_seconds,
    ) {
    }

    /** @param array<string, mixed> $s */
    public static function of(array $s): self
    {
        /** @var list<array{date: string, started: int, completed: int}> $timeline */
        $timeline = $s['timeline'];
        /** @var list<array{source: string, count: int}> $bySource */
        $bySource = $s['by_source'];
        /** @var array{avg: float|null, median: float|null} $duration */
        $duration = $s['duration_seconds'];

        return new self((int) $s['total'], (int) $s['completed'], (float) $s['completion_rate'], $timeline, $bySource, $duration);
    }
}
