<?php

namespace App\Tests\Unit\Reporting;

use App\Reporting\Domain\Model\DashboardSelection;
use App\Reporting\Domain\Model\QuestionProfile;
use PHPUnit\Framework\TestCase;

/** PRD §7.10: cleaning up the LLM's choice of dashboard type and charts. */
final class DashboardSelectionCleanupTest extends TestCase
{
    public function testKeepsValidChartsAndNumbersThemInOrder(): void
    {
        $clean = DashboardSelection::clean('satisfaction', [
            ['chart_type' => 'donut', 'title' => 'Channel', 'question_ids' => ['q-radio']],
            ['chart_type' => 'gauge', 'title' => 'Score', 'question_ids' => ['q-range']],
            ['chart_type' => 'line', 'title' => 'Over time', 'question_ids' => []],
        ], self::questions());

        self::assertSame('satisfaction', $clean['type']);
        self::assertSame(['donut', 'gauge', 'line'], array_column($clean['charts'], 'chart_type'));
        self::assertSame(['c1', 'c2', 'c3'], array_column($clean['charts'], 'id'), 'each chart gets a stable id');
        self::assertSame([0, 1, 2], array_column($clean['charts'], 'order'));
        self::assertSame(['q-radio'], $clean['charts'][0]['question_ids']);
    }

    public function testDiscardsChartsNotAllowedForTheTypeAndUnknownChartTypes(): void
    {
        $clean = DashboardSelection::clean('profiling', [
            ['chart_type' => 'gauge', 'title' => 'Score', 'question_ids' => ['q-range']],
            ['chart_type' => 'pie3d', 'title' => 'Nope', 'question_ids' => ['q-radio']],
            ['chart_type' => 'bar', 'title' => 'Channel', 'question_ids' => ['q-radio']],
        ], self::questions());

        self::assertSame(['bar'], array_column($clean['charts'], 'chart_type'), 'PRD §7.10: charts not allowed for the type are discarded');
    }

    public function testDiscardsUnknownAndDuplicateQuestions(): void
    {
        $clean = DashboardSelection::clean('opinion', [
            ['chart_type' => 'donut', 'title' => 'Ghost', 'question_ids' => ['q-missing']],
            ['chart_type' => 'stacked_bar', 'title' => 'Twice', 'question_ids' => ['q-agree-1', 'q-agree-1']],
            ['chart_type' => 'bar', 'title' => 'Channel', 'question_ids' => ['q-radio']],
            ['chart_type' => 'bar', 'title' => 'Channel again', 'question_ids' => ['q-radio']],
        ], self::questions());

        self::assertSame(['Channel'], array_column($clean['charts'], 'title'), 'unknown questions, a question repeated in a chart and a repeated chart are discarded');
    }

    public function testDiscardsIncompatibleControlTypes(): void
    {
        $clean = DashboardSelection::clean('opinion', [
            ['chart_type' => 'donut', 'title' => 'Comments', 'question_ids' => ['q-text']],
            ['chart_type' => 'histogram', 'title' => 'Channel', 'question_ids' => ['q-radio']],
            ['chart_type' => 'histogram', 'title' => 'Score', 'question_ids' => ['q-range']],
        ], self::questions());

        self::assertSame(['Score'], array_column($clean['charts'], 'title'), 'free text never charts; a histogram needs a numeric scale');
    }

    public function testDiscardsOutOfRangeQuestionCounts(): void
    {
        $clean = DashboardSelection::clean('opinion', [
            ['chart_type' => 'donut', 'title' => 'Two', 'question_ids' => ['q-radio', 'q-select']],
            ['chart_type' => 'heatmap', 'title' => 'One', 'question_ids' => ['q-agree-1']],
            ['chart_type' => 'line', 'title' => 'With a question', 'question_ids' => ['q-radio']],
            ['chart_type' => 'heatmap', 'title' => 'Agreement', 'question_ids' => ['q-agree-1', 'q-agree-2']],
        ], self::questions());

        self::assertSame(['Agreement'], array_column($clean['charts'], 'title'));
    }

    public function testHeatmapAndStackedBarNeedTheSameScale(): void
    {
        $clean = DashboardSelection::clean('opinion', [
            ['chart_type' => 'heatmap', 'title' => 'Mixed', 'question_ids' => ['q-agree-1', 'q-radio']],
            ['chart_type' => 'stacked_bar', 'title' => 'Mixed ranges', 'question_ids' => ['q-range', 'q-range-5']],
            ['chart_type' => 'stacked_bar', 'title' => 'Agreement', 'question_ids' => ['q-agree-1', 'q-agree-2']],
        ], self::questions());

        self::assertSame(['Agreement'], array_column($clean['charts'], 'title'), 'PRD §7.10: every question of a heatmap or stacked bar shares one answer scale');
    }

    public function testATypeUsedMoreThanTwiceSwitchesToASiblingOfTheSameFamily(): void
    {
        $clean = DashboardSelection::clean('opinion', [
            ['chart_type' => 'donut', 'title' => 'A', 'question_ids' => ['q-radio']],
            ['chart_type' => 'donut', 'title' => 'B', 'question_ids' => ['q-select']],
            ['chart_type' => 'donut', 'title' => 'C', 'question_ids' => ['q-check']],
        ], self::questions());

        self::assertSame(['donut', 'donut', 'bar'], array_column($clean['charts'], 'chart_type'), 'PRD §7.10: at most 2 of a type; the excess moves to a sibling');
    }

    public function testAnExcessChartWithoutAFreeSiblingIsDiscarded(): void
    {
        $clean = DashboardSelection::clean('eligibility', [
            ['chart_type' => 'line', 'title' => 'A', 'question_ids' => []],
            ['chart_type' => 'kpi', 'title' => 'K1', 'question_ids' => ['q-range']],
            ['chart_type' => 'kpi', 'title' => 'K2', 'question_ids' => ['q-range-5']],
            ['chart_type' => 'kpi', 'title' => 'K3', 'question_ids' => ['q-radio']],
            ['chart_type' => 'gauge', 'title' => 'G1', 'question_ids' => ['q-range']],
            ['chart_type' => 'gauge', 'title' => 'G2', 'question_ids' => ['q-range-5']],
        ], self::questions());

        self::assertSame(['line', 'kpi', 'kpi', 'gauge', 'gauge'], array_column($clean['charts'], 'chart_type'), 'eligibility allows kpi and gauge only in its numeric family, and a radio of labels is not numeric');
    }

    public function testKeepsAtMostTenCharts(): void
    {
        $charts = [];
        foreach (['donut', 'bar', 'horizontal_bar', 'treemap'] as $type) {
            foreach (['q-radio', 'q-select'] as $question) {
                $charts[] = ['chart_type' => $type, 'title' => $type.$question, 'question_ids' => [$question]];
            }
        }
        $charts[] = ['chart_type' => 'kpi', 'title' => 'k1', 'question_ids' => ['q-range']];
        $charts[] = ['chart_type' => 'kpi', 'title' => 'k2', 'question_ids' => ['q-range-5']];
        $charts[] = ['chart_type' => 'gauge', 'title' => 'g1', 'question_ids' => ['q-range']];
        $charts[] = ['chart_type' => 'line', 'title' => 'l1', 'question_ids' => []];

        $clean = DashboardSelection::clean('opinion', $charts, self::questions());

        self::assertCount(10, $clean['charts'], 'PRD §7.10: at most 10 charts');
        self::assertSame('k2', $clean['charts'][9]['title'], 'the first ten in the order the LLM chose');
    }

    public function testTierDistributionOnlyForADiagnostic(): void
    {
        $charts = [['chart_type' => 'tier_distribution', 'title' => 'Tiers', 'question_ids' => []]];

        self::assertSame([], DashboardSelection::clean('knowledge', $charts, self::questions())['charts']);
        self::assertCount(1, DashboardSelection::clean('knowledge', $charts, self::questions(), diagnostic: true)['charts']);
    }

    public function testAnUnknownDashboardTypeFallsBackToOpinion(): void
    {
        $clean = DashboardSelection::clean('marketing', [
            ['chart_type' => 'bar', 'title' => 'Channel', 'question_ids' => ['q-radio']],
        ], self::questions());

        self::assertSame('opinion', $clean['type']);
        self::assertCount(1, $clean['charts']);
    }

    public function testNothingSurvivesMeansNoCharts(): void
    {
        $clean = DashboardSelection::clean('satisfaction', [
            ['chart_type' => 'donut', 'title' => 'Comments', 'question_ids' => ['q-text']],
            'not a chart',
        ], self::questions());

        self::assertSame([], $clean['charts'], 'the caller answers 502 DASHBOARD_GENERATION_FAILED');
    }

    public function testATitleIsTrimmedAndShortened(): void
    {
        $clean = DashboardSelection::clean('opinion', [
            ['chart_type' => 'bar', 'title' => '  '.str_repeat('x', 200).'  ', 'question_ids' => ['q-radio']],
        ], self::questions());

        self::assertSame(120, mb_strlen($clean['charts'][0]['title']));
    }

    /** @return list<QuestionProfile> */
    private static function questions(): array
    {
        $agree = [['label' => 'Disagree', 'value' => '1'], ['label' => 'Neutral', 'value' => '2'], ['label' => 'Agree', 'value' => '3']];

        return array_values(array_filter(array_map(QuestionProfile::fromQuestion(...), [
            ['id' => 'q-radio', 'title' => 'Channel', 'options' => [['type' => 'radio', 'options' => [['label' => 'Web'], ['label' => 'Store']]]]],
            ['id' => 'q-select', 'title' => 'Country', 'options' => [['type' => 'select', 'options' => [['label' => 'CO', 'value' => 'co'], ['label' => 'MX', 'value' => 'mx']]]]],
            ['id' => 'q-check', 'title' => 'Interests', 'options' => [['type' => 'checkbox', 'options' => [['label' => 'A'], ['label' => 'B']]]]],
            ['id' => 'q-range', 'title' => 'Score', 'options' => [['type' => 'range', 'validations' => [['type' => 'min', 'value' => 0], ['type' => 'max', 'value' => 10]]]]],
            ['id' => 'q-range-5', 'title' => 'Ease', 'options' => [['type' => 'range', 'validations' => [['type' => 'min', 'value' => 1], ['type' => 'max', 'value' => 5]]]]],
            ['id' => 'q-agree-1', 'title' => 'Fast', 'options' => [['type' => 'radio', 'options' => $agree]]],
            ['id' => 'q-agree-2', 'title' => 'Kind', 'options' => [['type' => 'radio', 'options' => $agree]]],
            ['id' => 'q-text', 'title' => 'Comments', 'options' => [['type' => 'text']]],
            ['id' => 'q-message', 'title' => 'Thanks', 'options' => [['type' => 'message']]],
        ])));
    }
}
