<?php

namespace App\Tests\Unit\Reporting;

use App\Reporting\Domain\Model\AnswerStatistics;
use App\Reporting\Domain\Model\QuestionProfile;
use PHPUnit\Framework\TestCase;

/** PRD §10.9: the dashboard data computed from the stored sessions (the usage/analytics service absorbed, §13.8). */
final class AnswerStatisticsTest extends TestCase
{
    public function testQuartilesInterpolateBetweenValues(): void
    {
        self::assertSame(
            ['count' => 4, 'avg' => 2.5, 'min' => 1.0, 'q1' => 1.75, 'median' => 2.5, 'q3' => 3.25, 'max' => 4.0],
            AnswerStatistics::numeric([4, 1, 3, 2]),
        );
        self::assertSame(
            ['count' => 1, 'avg' => 7.0, 'min' => 7.0, 'q1' => 7.0, 'median' => 7.0, 'q3' => 7.0, 'max' => 7.0],
            AnswerStatistics::numeric([7]),
        );
        self::assertNull(AnswerStatistics::numeric([]));
    }

    public function testSessionTotalsCompletionTimelineSourcesAndDurations(): void
    {
        $data = AnswerStatistics::compute([
            self::session('2026-09-01T10:00:00Z', '2026-09-01T10:01:40Z', 'completed', ['q-score' => '9']),
            self::session('2026-09-01T11:00:00Z', '2026-09-01T11:05:00Z', 'filled_out', ['q-score' => '3'], assignation: true),
            self::session('2026-09-02T09:00:00Z', null, 'filling', []),
            self::session('2026-09-02T09:30:00Z', '2026-09-02T09:32:20Z', 'submitted', ['q-score' => '10']),
        ], self::questions(), []);

        $sessions = $data['sessions'];
        self::assertSame(4, $sessions['total']);
        self::assertSame(3, $sessions['completed'], 'a session counts as completed once the respondent submitted it (legacy "submitted" too)');
        self::assertSame(0.75, $sessions['completion_rate']);
        self::assertSame([
            ['date' => '2026-09-01', 'started' => 2, 'completed' => 2],
            ['date' => '2026-09-02', 'started' => 2, 'completed' => 1],
        ], $sessions['timeline']);
        self::assertSame([['source' => 'link', 'count' => 3], ['source' => 'assignation', 'count' => 1]], $sessions['by_source']);
        self::assertSame(['avg' => 180.0, 'median' => 140.0], $sessions['duration_seconds'], 'durations of 100, 300 and 140 seconds');
    }

    public function testPerQuestionCountsValuesAndNumericSummary(): void
    {
        $data = AnswerStatistics::compute([
            self::session('2026-09-01T10:00:00Z', '2026-09-01T10:01:00Z', 'completed', ['q-score' => '9', 'q-channel' => 'web', 'q-topics' => ['a', 'b'], 'q-comment' => 'Great']),
            self::session('2026-09-01T10:00:00Z', '2026-09-01T10:01:00Z', 'completed', ['q-score' => '3', 'q-channel' => 'web', 'q-topics' => ['b']]),
            self::session('2026-09-01T10:00:00Z', null, 'filling', ['q-channel' => 'store']),
        ], self::questions(), []);

        $byId = array_column($data['questions'], null, 'question_id');
        self::assertSame(['q-score', 'q-channel', 'q-topics', 'q-comment'], array_keys($byId), 'every answerable question in order; message slides are left out');

        self::assertSame(2, $byId['q-score']['answers_count']);
        self::assertSame([['value' => '3', 'count' => 1], ['value' => '9', 'count' => 1]], $byId['q-score']['values']);
        self::assertSame(6.0, $byId['q-score']['numeric']['avg']);

        self::assertSame(3, $byId['q-channel']['answers_count']);
        self::assertSame([['value' => 'web', 'count' => 2], ['value' => 'store', 'count' => 1]], $byId['q-channel']['values'], 'sorted by count');
        self::assertNull($byId['q-channel']['numeric'], 'labels are not numbers');

        self::assertSame([['value' => 'b', 'count' => 2], ['value' => 'a', 'count' => 1]], $byId['q-topics']['values'], 'each checked option counts');

        self::assertSame(1, $byId['q-comment']['answers_count']);
        self::assertSame([], $byId['q-comment']['values'], 'free text is counted, never listed');
    }

    public function testTiersCountTheBandOfEachDiagnosticScore(): void
    {
        $tiers = [['id' => 't1', 'name' => 'Beginner', 'min' => 0, 'max' => 10], ['id' => 't2', 'name' => 'Expert', 'min' => 11, 'max' => 20]];
        $data = AnswerStatistics::compute([], self::questions(), [
            ['score' => ['value' => 4, 'max' => 20], 'tiers' => $tiers],
            ['score' => ['value' => 15, 'max' => 20], 'tiers' => $tiers],
            ['score' => ['value' => 18, 'max' => 20], 'tiers' => $tiers],
        ]);

        self::assertSame([
            ['tier_id' => 't1', 'name' => 'Beginner', 'count' => 1],
            ['tier_id' => 't2', 'name' => 'Expert', 'count' => 2],
        ], $data['tiers']);
    }

    public function testNoSessionsGivesZerosNotErrors(): void
    {
        $data = AnswerStatistics::compute([], self::questions(), []);

        self::assertSame(0, $data['sessions']['total']);
        self::assertSame(0.0, $data['sessions']['completion_rate']);
        self::assertSame(['avg' => null, 'median' => null], $data['sessions']['duration_seconds']);
        self::assertSame(0, $data['questions'][0]['answers_count']);
    }

    /** @return list<QuestionProfile> */
    private static function questions(): array
    {
        return array_values(array_filter(array_map(QuestionProfile::fromQuestion(...), self::questionnaire())));
    }

    /** @return list<array<string, mixed>> */
    private static function questionnaire(): array
    {
        return [
            ['id' => 'q-intro', 'title' => 'Welcome', 'options' => [['type' => 'message']]],
            ['id' => 'q-score', 'title' => 'Score', 'options' => [['type' => 'range']]],
            ['id' => 'q-channel', 'title' => 'Channel', 'options' => [['type' => 'radio', 'options' => [['label' => 'Web', 'value' => 'web'], ['label' => 'Store', 'value' => 'store']]]]],
            ['id' => 'q-topics', 'title' => 'Topics', 'options' => [['type' => 'checkbox', 'options' => [['label' => 'A', 'value' => 'a'], ['label' => 'B', 'value' => 'b']]]]],
            ['id' => 'q-comment', 'title' => 'Comment', 'options' => [['type' => 'text']]],
        ];
    }

    /**
     * @param array<string, string|list<string>> $values
     *
     * @return array<string, mixed>
     */
    private static function session(string $startedAt, ?string $endedAt, string $status, array $values, bool $assignation = false): array
    {
        $questions = [];
        foreach (self::questionnaire() as $question) {
            $question['options'][0]['value'] = $values[$question['id']] ?? null;
            $questions[] = $question;
        }

        return [
            'session_id' => md5($startedAt.$status.json_encode($values)),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'status' => $status,
            'assignations_id' => $assignation ? 'a-1' : null,
            'questions' => $questions,
        ];
    }
}
