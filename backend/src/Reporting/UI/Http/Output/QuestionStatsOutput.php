<?php

namespace App\Reporting\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * One question's answers (PRD §10.9): how many sessions answered it, each value with its count (choices and scales
 * only, most frequent first) and, for a number scale, {count, avg, min, q1, median, q3, max}.
 */
final class QuestionStatsOutput
{
    /**
     * @param list<array{value: string, count: int}>                                                          $values
     * @param array{count: int, avg: float, min: float, q1: float, median: float, q3: float, max: float}|null $numeric
     */
    public function __construct(
        public readonly string $question_id,
        public readonly int $answers_count,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object', required: ['value', 'count'], properties: [
            new OA\Property(property: 'value', type: 'string'),
            new OA\Property(property: 'count', type: 'integer'),
        ]))]
        public readonly array $values,
        #[OA\Property(type: 'object', nullable: true, required: ['count', 'avg', 'min', 'q1', 'median', 'q3', 'max'], properties: [
            new OA\Property(property: 'count', type: 'integer'),
            new OA\Property(property: 'avg', type: 'number'),
            new OA\Property(property: 'min', type: 'number'),
            new OA\Property(property: 'q1', type: 'number'),
            new OA\Property(property: 'median', type: 'number'),
            new OA\Property(property: 'q3', type: 'number'),
            new OA\Property(property: 'max', type: 'number'),
        ])]
        public readonly ?array $numeric,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return list<self>
     */
    public static function list(array $questions): array
    {
        return array_map(static function (array $q): self {
            /** @var list<array{value: string, count: int}> $values */
            $values = $q['values'];
            /** @var array{count: int, avg: float, min: float, q1: float, median: float, q3: float, max: float}|null $numeric */
            $numeric = $q['numeric'];

            return new self((string) $q['question_id'], (int) $q['answers_count'], $values, $numeric);
        }, $questions);
    }
}
