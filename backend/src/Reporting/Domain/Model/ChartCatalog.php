<?php

namespace App\Reporting\Domain\Model;

/**
 * The dashboard catalog (PRD §6.19, §7.10): the charts each dashboard type may use, the family each chart belongs to
 * (an excess chart switches to a sibling of its family), how many questions it takes and which questions fit it.
 * The same text is given to the LLM as {dashboard_catalog}.
 */
final class ChartCatalog
{
    public const MAX_CHARTS = 10;
    public const MAX_PER_TYPE = 2;
    public const FALLBACK_TYPE = 'opinion';

    /** Families, siblings in order of preference. */
    public const FAMILIES = [
        'categorical' => ['donut', 'bar', 'horizontal_bar', 'treemap'],
        'numeric' => ['kpi', 'gauge', 'histogram', 'boxplot'],
        'comparison' => ['stacked_bar', 'heatmap', 'ranking_avg'],
        'overview' => ['line', 'tier_distribution'],
    ];

    /** The charts each dashboard type may use. */
    public const ALLOWED = [
        'satisfaction' => ['kpi', 'gauge', 'line', 'donut', 'bar', 'horizontal_bar', 'stacked_bar', 'histogram', 'boxplot', 'ranking_avg', 'heatmap'],
        'knowledge' => ['kpi', 'line', 'donut', 'bar', 'horizontal_bar', 'histogram', 'boxplot', 'ranking_avg', 'tier_distribution'],
        'profiling' => ['kpi', 'line', 'donut', 'bar', 'horizontal_bar', 'treemap', 'stacked_bar', 'heatmap'],
        'recommendations' => ['kpi', 'line', 'donut', 'bar', 'horizontal_bar', 'treemap', 'ranking_avg'],
        'eligibility' => ['kpi', 'gauge', 'line', 'donut', 'bar', 'horizontal_bar', 'tier_distribution'],
        'opinion' => ['kpi', 'gauge', 'line', 'donut', 'bar', 'horizontal_bar', 'stacked_bar', 'treemap', 'histogram', 'boxplot', 'ranking_avg', 'heatmap'],
    ];

    /** [min, max] questions per chart. */
    public const QUESTION_COUNTS = [
        'kpi' => [1, 1], 'gauge' => [1, 1], 'histogram' => [1, 1], 'boxplot' => [1, 1],
        'donut' => [1, 1], 'bar' => [1, 1], 'horizontal_bar' => [1, 1], 'treemap' => [1, 1],
        'stacked_bar' => [2, 8], 'heatmap' => [2, 8], 'ranking_avg' => [2, 10],
        'line' => [0, 0], 'tier_distribution' => [0, 0],
    ];

    /** What each chart shows, for the LLM. */
    private const DESCRIPTIONS = [
        'kpi' => 'one number: the average of a numeric question, or the share of the top answer of a choice question',
        'gauge' => 'the average of one numeric question on its scale',
        'line' => 'sessions started and completed per day (no questions)',
        'donut' => 'the share of each answer of one choice question (few options)',
        'bar' => 'the count of each answer of one choice question',
        'horizontal_bar' => 'the count of each answer of one choice question with long labels or many options',
        'stacked_bar' => 'the answer distribution of 2-8 questions that share the same scale',
        'treemap' => 'the share of each answer of one choice question with many options',
        'histogram' => 'the distribution of one numeric question',
        'boxplot' => 'min, quartiles and max of one numeric question',
        'ranking_avg' => '2-10 numeric questions ranked by their average',
        'heatmap' => 'answer counts of 2-8 questions that share the same scale, one row per question',
        'tier_distribution' => 'how many respondents fell in each diagnostic tier (diagnostics only, no questions)',
    ];

    public static function familyOf(string $chartType): ?string
    {
        foreach (self::FAMILIES as $family => $members) {
            if (\in_array($chartType, $members, true)) {
                return $family;
            }
        }

        return null;
    }

    public static function allows(string $dashboardType, string $chartType): bool
    {
        return \in_array($chartType, self::ALLOWED[$dashboardType] ?? [], true);
    }

    /**
     * Whether these questions fit the chart: their number and their control types (PRD §7.10).
     *
     * @param list<QuestionProfile> $questions
     */
    public static function fits(string $chartType, array $questions): bool
    {
        [$min, $max] = self::QUESTION_COUNTS[$chartType] ?? [1, 0];
        if (\count($questions) < $min || \count($questions) > $max) {
            return false;
        }
        foreach ($questions as $question) {
            $fits = match ($chartType) {
                'donut', 'bar', 'horizontal_bar', 'treemap' => $question->isSelection(),
                'kpi' => $question->isSelection() || $question->isNumeric(),
                'gauge', 'histogram', 'boxplot', 'ranking_avg' => $question->isNumeric(),
                'stacked_bar', 'heatmap' => $question->hasCountableValues(),
                default => false,
            };
            if (!$fits) {
                return false;
            }
        }
        if (\in_array($chartType, ['stacked_bar', 'heatmap'], true)) {
            return 1 === \count(array_unique(array_map(static fn (QuestionProfile $q): string => $q->scale(), $questions)));
        }

        return true;
    }

    /** The {dashboard_catalog} placeholder of the system prompt. */
    public static function describe(): string
    {
        $lines = ['Dashboard types and the charts each may use:'];
        foreach (self::ALLOWED as $type => $charts) {
            $lines[] = \sprintf('- %s: %s', $type, implode(', ', $charts));
        }
        $lines[] = '';
        $lines[] = 'Charts (questions each takes):';
        foreach (self::DESCRIPTIONS as $chart => $description) {
            [$min, $max] = self::QUESTION_COUNTS[$chart];
            $lines[] = \sprintf('- %s (%s): %s', $chart, $min === $max ? (string) $min : $min.'-'.$max, $description);
        }
        $lines[] = '';
        $lines[] = \sprintf('At most %d charts, each chart type at most %d times.', self::MAX_CHARTS, self::MAX_PER_TYPE);

        return implode("\n", $lines);
    }
}
