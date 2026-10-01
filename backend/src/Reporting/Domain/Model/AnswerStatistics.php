<?php

namespace App\Reporting\Domain\Model;

use App\Shared\Domain\Document\Questions;

/**
 * The dashboard data of a questionnaire (PRD §10.9), computed from its stored sessions — the analytics
 * service is absorbed (§13.8):
 *
 *     sessions:  {total, completed, completion_rate, timeline: [{date, started, completed}], by_source,
 *                 duration_seconds: {avg, median}}
 *     questions: [{question_id, answers_count, values: [{value, count}], numeric: {count, avg, min, q1, median, q3, max}}]
 *     tiers:     [{tier_id, name, count}]   (diagnostics: the band of each session's score, for tier_distribution)
 *
 * A session counts as completed once the respondent submitted it (filled_out, processing or completed).
 */
final class AnswerStatistics
{
    private const SUBMITTED = ['filled_out', 'submitted', 'processing', 'completed'];

    /**
     * @param list<array<string, mixed>> $sessions    session data (started_at, ended_at, status, assignations_id, questions)
     * @param list<QuestionProfile>      $questions   the questionnaire's answerable questions, in order
     * @param list<array<string, mixed>> $diagnostics the diagnostic results of its sessions
     *
     * @return array{sessions: array<string, mixed>, questions: list<array<string, mixed>>, tiers: list<array{tier_id: string, name: string, count: int}>}
     */
    public static function compute(array $sessions, array $questions, array $diagnostics): array
    {
        return [
            'sessions' => self::sessions($sessions),
            'questions' => array_map(static fn (QuestionProfile $q): array => self::question($q, $sessions), $questions),
            'tiers' => self::tiers($diagnostics),
        ];
    }

    /**
     * count, average, min, quartiles (linear interpolation) and max; null without values.
     *
     * @param list<int|float> $values
     *
     * @return array{count: int, avg: float, min: float, q1: float, median: float, q3: float, max: float}|null
     */
    public static function numeric(array $values): ?array
    {
        if ([] === $values) {
            return null;
        }
        $values = array_map('floatval', $values);
        sort($values);

        return [
            'count' => \count($values),
            'avg' => round(array_sum($values) / \count($values), 4),
            'min' => $values[0],
            'q1' => self::quantile($values, 0.25),
            'median' => self::quantile($values, 0.5),
            'q3' => self::quantile($values, 0.75),
            'max' => $values[\count($values) - 1],
        ];
    }

    public static function isSubmitted(string $status): bool
    {
        return \in_array($status, self::SUBMITTED, true);
    }

    /**
     * @param list<array<string, mixed>> $sessions
     *
     * @return array<string, mixed>
     */
    private static function sessions(array $sessions): array
    {
        $total = \count($sessions);
        $completed = 0;
        $timeline = [];
        $sources = ['link' => 0, 'assignation' => 0];
        $durations = [];
        foreach ($sessions as $session) {
            $started = self::date($session['started_at'] ?? null);
            if (null !== $started) {
                $timeline[$started] ??= ['date' => $started, 'started' => 0, 'completed' => 0];
                ++$timeline[$started]['started'];
            }
            ++$sources[null === ($session['assignations_id'] ?? null) ? 'link' : 'assignation'];
            if (!self::isSubmitted((string) ($session['status'] ?? ''))) {
                continue;
            }
            ++$completed;
            $ended = self::date($session['ended_at'] ?? null) ?? $started;
            if (null !== $ended) {
                $timeline[$ended] ??= ['date' => $ended, 'started' => 0, 'completed' => 0];
                ++$timeline[$ended]['completed'];
            }
            $seconds = self::seconds($session['started_at'] ?? null, $session['ended_at'] ?? null);
            if (null !== $seconds) {
                $durations[] = $seconds;
            }
        }
        ksort($timeline);
        $bySource = [];
        foreach ($sources as $source => $count) {
            if ($count > 0) {
                $bySource[] = ['source' => $source, 'count' => $count];
            }
        }
        sort($durations);

        return [
            'total' => $total,
            'completed' => $completed,
            'completion_rate' => 0 === $total ? 0.0 : round($completed / $total, 4),
            'timeline' => array_values($timeline),
            'by_source' => $bySource,
            'duration_seconds' => [
                'avg' => [] === $durations ? null : round(array_sum($durations) / \count($durations), 2),
                'median' => [] === $durations ? null : self::quantile(array_map('floatval', $durations), 0.5),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $sessions
     *
     * @return array<string, mixed>
     */
    private static function question(QuestionProfile $profile, array $sessions): array
    {
        $answers = 0;
        $counts = [];
        $numbers = [];
        foreach ($sessions as $session) {
            $question = self::find((array) ($session['questions'] ?? []), $profile->id);
            if (null === $question || !Questions::isAnswered($question)) {
                continue;
            }
            ++$answers;
            if (!$profile->hasCountableValues()) {
                continue;
            }
            foreach (Questions::values($question) as $value) {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
                if ($profile->isNumeric() && is_numeric($value)) {
                    $numbers[] = (float) $value;
                }
            }
        }
        $values = [];
        foreach ($counts as $value => $count) {
            $values[] = ['value' => (string) $value, 'count' => $count];
        }
        usort($values, static function (array $a, array $b): int {
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }
            if (is_numeric($a['value']) && is_numeric($b['value'])) {
                return (float) $a['value'] <=> (float) $b['value'];
            }

            return strcmp($a['value'], $b['value']);
        });

        return [
            'question_id' => $profile->id,
            'answers_count' => $answers,
            'values' => $values,
            'numeric' => $profile->isNumeric() ? self::numeric($numbers) : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     *
     * @return list<array{tier_id: string, name: string, count: int}>
     */
    private static function tiers(array $diagnostics): array
    {
        $tiers = [];
        foreach ($diagnostics as $diagnostic) {
            $score = (float) ($diagnostic['score']['value'] ?? 0);
            foreach ((array) ($diagnostic['tiers'] ?? []) as $tier) {
                if (!\is_array($tier) || $score < (float) ($tier['min'] ?? 0) || $score > (float) ($tier['max'] ?? 0)) {
                    continue;
                }
                $id = (string) ($tier['id'] ?? '');
                $tiers[$id] ??= ['tier_id' => $id, 'name' => (string) ($tier['name'] ?? ''), 'count' => 0, 'min' => (float) ($tier['min'] ?? 0)];
                ++$tiers[$id]['count'];
                break;
            }
        }
        usort($tiers, static fn (array $a, array $b): int => $a['min'] <=> $b['min']);

        return array_map(static fn (array $t): array => ['tier_id' => $t['tier_id'], 'name' => $t['name'], 'count' => $t['count']], $tiers);
    }

    /**
     * @param array<int|string, mixed> $questions
     *
     * @return array<string, mixed>|null
     */
    private static function find(array $questions, string $id): ?array
    {
        foreach ($questions as $question) {
            if (\is_array($question) && ($question['id'] ?? null) === $id) {
                return $question;
            }
        }

        return null;
    }

    /** @param list<float> $sorted */
    private static function quantile(array $sorted, float $p): float
    {
        $position = (\count($sorted) - 1) * $p;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        return round($sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($position - $lower), 4);
    }

    private static function date(mixed $iso): ?string
    {
        return \is_string($iso) && \strlen($iso) >= 10 ? substr($iso, 0, 10) : null;
    }

    private static function seconds(mixed $start, mixed $end): ?float
    {
        if (!\is_string($start) || !\is_string($end)) {
            return null;
        }
        $seconds = strtotime($end) - strtotime($start);

        return $seconds >= 0 ? (float) $seconds : null;
    }
}
