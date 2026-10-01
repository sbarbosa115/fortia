<?php

namespace App\Reporting\Infrastructure\Llm;

use App\Reporting\Application\Command\GenerateDashboardHandler;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Infrastructure\Llm\Fake\FakeLlmResponder;

/**
 * The offline choice of dashboard (LLM_PROVIDER=fake): a deterministic, sensible layout from the questions in the
 * request's context — the timeline, a gauge and a histogram for each numeric scale, a donut or bar for each choice
 * question, a heatmap when several choice questions share a scale, and the tiers of a diagnostic. The cleanup of
 * PRD §7.10 still runs over it (at most 2 per type, 10 in all). Charts without a question have no title, so the
 * console shows the chart type in the viewer's language.
 */
final class SelectDashboardTypeResponder implements FakeLlmResponder
{
    public function supports(LlmRequest $request): bool
    {
        return GenerateDashboardHandler::PROMPT_KEY === $request->purpose;
    }

    public function respond(LlmRequest $request): LlmResponse
    {
        $questions = array_values(array_filter((array) ($request->context['questions'] ?? []), 'is_array'));
        $diagnostic = true === ($request->context['diagnostic'] ?? false);
        $charts = [['chart_type' => 'line', 'title' => '', 'question_ids' => []]];
        if ($diagnostic) {
            $charts[] = ['chart_type' => 'tier_distribution', 'title' => '', 'question_ids' => []];
        }

        $numeric = [];
        $choice = [];
        $scales = [];
        foreach ($questions as $question) {
            $id = (string) ($question['id'] ?? '');
            $title = (string) ($question['title'] ?? '');
            $type = (string) ($question['type'] ?? '');
            if ('range' === $type || self::numericOptions($question)) {
                $numeric[] = ['id' => $id, 'title' => $title];
            } elseif (\in_array($type, ['radio', 'select', 'checkbox', 'ranking'], true)) {
                $choice[] = ['id' => $id, 'title' => $title, 'many' => \count((array) ($question['options'] ?? [])) > 5];
            }
            if ('radio' === $type || 'select' === $type) {
                $scales[implode('|', array_column((array) ($question['options'] ?? []), 'value'))][] = $id;
            }
        }

        foreach ($numeric as $i => $question) {
            $charts[] = ['chart_type' => 0 === $i && !$diagnostic ? 'gauge' : 'kpi', 'title' => $question['title'], 'question_ids' => [$question['id']]];
            $charts[] = ['chart_type' => 'histogram', 'title' => $question['title'], 'question_ids' => [$question['id']]];
        }
        foreach ($choice as $i => $question) {
            $charts[] = ['chart_type' => $question['many'] ? 'horizontal_bar' : (0 === $i % 2 ? 'donut' : 'bar'), 'title' => $question['title'], 'question_ids' => [$question['id']]];
        }
        foreach ($scales as $ids) {
            if (\count($ids) >= 2) {
                $charts[] = ['chart_type' => 'heatmap', 'title' => '', 'question_ids' => \array_slice($ids, 0, 8)];
                break;
            }
        }
        if (\count($numeric) >= 2) {
            $charts[] = ['chart_type' => 'ranking_avg', 'title' => '', 'question_ids' => \array_slice(array_column($numeric, 'id'), 0, 10)];
        }

        return LlmResponse::json(['type' => $diagnostic ? 'knowledge' : ([] !== $numeric ? 'satisfaction' : 'profiling'), 'charts' => $charts]);
    }

    /** @param array<string, mixed> $question */
    private static function numericOptions(array $question): bool
    {
        $options = (array) ($question['options'] ?? []);
        if ([] === $options || !\in_array($question['type'] ?? null, ['radio', 'select'], true)) {
            return false;
        }
        foreach ($options as $option) {
            if (!\is_array($option) || !is_numeric($option['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
