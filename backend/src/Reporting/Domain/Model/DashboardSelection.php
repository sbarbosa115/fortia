<?php

namespace App\Reporting\Domain\Model;

/**
 * Cleans up the LLM's choice of dashboard (PRD §7.10), in this order:
 *
 * 1. discard charts of an unknown type or not allowed for the dashboard type, with unknown or repeated questions,
 *    out-of-range question counts or incompatible control types (heatmap and stacked bar: one shared scale), and
 *    charts that repeat an earlier one;
 * 2. at most 2 charts of each type: the excess switches to a free sibling of the same family, or is discarded;
 * 3. at most 10 charts, in the LLM's order, numbered c1…cN.
 *
 * No chart left means the generation failed (the caller answers 502 and stores nothing).
 */
final class DashboardSelection
{
    private const TITLE_LENGTH = 120;

    /**
     * @param array<int|string, mixed> $charts     what the LLM returned: [{chart_type, title, question_ids}]
     * @param list<QuestionProfile>    $questions  the questionnaire's answerable questions
     * @param bool                     $diagnostic whether the questionnaire scores tiers (tier_distribution)
     *
     * @return array{type: string, charts: list<array{id: string, chart_type: string, title: string, question_ids: list<string>, order: int}>}
     */
    public static function clean(string $type, array $charts, array $questions, bool $diagnostic = false): array
    {
        $type = isset(ChartCatalog::ALLOWED[$type]) ? $type : ChartCatalog::FALLBACK_TYPE;
        $byId = [];
        foreach ($questions as $question) {
            $byId[$question->id] = $question;
        }

        $kept = [];
        $seen = [];
        $perType = [];
        foreach ($charts as $chart) {
            $candidate = self::candidate($chart, $byId);
            if (null === $candidate) {
                continue;
            }
            [$chartType, $title, $ids, $profiles] = $candidate;
            if ('tier_distribution' === $chartType && !$diagnostic) {
                continue;
            }
            if (!ChartCatalog::allows($type, $chartType) || !ChartCatalog::fits($chartType, $profiles)) {
                continue;
            }
            $questionsKey = implode(',', self::sorted($ids));
            if (isset($seen[$chartType.'|'.$questionsKey])) {
                continue;
            }
            $seen[$chartType.'|'.$questionsKey] = true;

            if (($perType[$chartType] ?? 0) >= ChartCatalog::MAX_PER_TYPE) {
                $chartType = self::sibling($type, $chartType, $profiles, $perType, $seen, $questionsKey);
                if (null === $chartType) {
                    continue;
                }
                $seen[$chartType.'|'.$questionsKey] = true;
            }
            $perType[$chartType] = ($perType[$chartType] ?? 0) + 1;
            $kept[] = ['chart_type' => $chartType, 'title' => $title, 'question_ids' => $ids];
            if (ChartCatalog::MAX_CHARTS === \count($kept)) {
                break;
            }
        }

        $out = [];
        foreach ($kept as $i => $chart) {
            $out[] = ['id' => 'c'.($i + 1), 'chart_type' => $chart['chart_type'], 'title' => $chart['title'], 'question_ids' => $chart['question_ids'], 'order' => $i];
        }

        return ['type' => $type, 'charts' => $out];
    }

    /**
     * The chart's type, title and questions, or null when it is malformed or names an unknown or repeated question.
     *
     * @param array<string, QuestionProfile> $byId
     *
     * @return array{0: string, 1: string, 2: list<string>, 3: list<QuestionProfile>}|null
     */
    private static function candidate(mixed $chart, array $byId): ?array
    {
        if (!\is_array($chart) || !\is_string($chart['chart_type'] ?? null) || null === ChartCatalog::familyOf($chart['chart_type'])) {
            return null;
        }
        $ids = [];
        $profiles = [];
        foreach ((array) ($chart['question_ids'] ?? []) as $id) {
            if (!\is_string($id) || !isset($byId[$id]) || \in_array($id, $ids, true)) {
                return null;
            }
            $ids[] = $id;
            $profiles[] = $byId[$id];
        }
        $title = \is_string($chart['title'] ?? null) ? trim($chart['title']) : '';

        return [$chart['chart_type'], mb_substr($title, 0, self::TITLE_LENGTH), $ids, $profiles];
    }

    /**
     * The first sibling of the chart's family the dashboard type allows, that is not used twice yet and fits.
     *
     * @param list<QuestionProfile> $profiles
     * @param array<string, int>    $perType
     * @param array<string, true>   $seen     the charts already kept, so a switch never repeats one
     */
    private static function sibling(string $type, string $chartType, array $profiles, array $perType, array $seen, string $questionsKey): ?string
    {
        foreach (ChartCatalog::FAMILIES[(string) ChartCatalog::familyOf($chartType)] as $sibling) {
            if ($sibling !== $chartType
                && !isset($seen[$sibling.'|'.$questionsKey])
                && ($perType[$sibling] ?? 0) < ChartCatalog::MAX_PER_TYPE
                && ChartCatalog::allows($type, $sibling)
                && ChartCatalog::fits($sibling, $profiles)) {
                return $sibling;
            }
        }

        return null;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
