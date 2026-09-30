<?php

namespace App\Responses\Domain\Scoring;

use App\Shared\Domain\Document\ControlType;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Document\Scoring;

/**
 * The score of a diagnostic session (PRD §7.7, §6.10 DiagnosticResult):
 *
 * - only questions with a category and a maximum > 0 count;
 * - a category's score is the sum of the selected values; the total is the sum of the categories, rounded half-up;
 * - the tier is the band [min, max] that contains the total (overlapping bands: the highest min wins, §9.12);
 * - the recommendations and the action plan come from the reached tier, or from the nearest lower tier that has
 *   them, never from a higher one (§9.12);
 * - only the visible tiers are returned.
 */
final class DiagnosticScoring
{
    /**
     * @param list<array<string, mixed>> $questions the session's questions, with their values
     * @param array<string, mixed>       $config    {tiers, recommendations, action_plan} (PRD §6.7)
     *
     * @return array{type: string, score: array{value: int, max: float}, categories: list<array{id: string, name: string, score: float, max: float}>, tiers: list<array<string, mixed>>, recommendations: list<array{tier_id: string, recommendation: string}>, action_plan: list<array{tier_id: string, action: string}>}
     */
    public static function score(array $questions, array $config): array
    {
        /** @var array<string, array{id: string, name: string, score: float, max: float}> $categories */
        $categories = [];
        foreach ($questions as $question) {
            $category = trim((string) ($question['category'] ?? ''));
            $max = Scoring::maxScore($question);
            if ('' === $category || $max <= 0) {
                continue;
            }
            $categories[$category] ??= ['id' => $category, 'name' => $category, 'score' => 0.0, 'max' => 0.0];
            $categories[$category]['score'] += self::questionScore($question);
            $categories[$category]['max'] += $max;
        }
        $categories = array_values(array_map(static fn (array $c): array => [
            'id' => $c['id'],
            'name' => $c['name'],
            'score' => round($c['score'], 2),
            'max' => round($c['max'], 2),
        ], $categories));

        $total = Scoring::roundHalfUp(array_sum(array_column($categories, 'score')));
        $max = round(array_sum(array_column($categories, 'max')), 2);

        $tiers = self::rows($config['tiers'] ?? []);
        $reached = self::tierFor($total, $tiers);

        return [
            'type' => 'diagnostic',
            'score' => ['value' => $total, 'max' => $max],
            'categories' => $categories,
            'tiers' => array_values(array_filter($tiers, static fn (array $t): bool => false !== ($t['visible'] ?? true))),
            'recommendations' => self::textsFor($total, $reached, $tiers, self::rows($config['recommendations'] ?? []), 'recommendation'),
            'action_plan' => self::textsFor($total, $reached, $tiers, self::rows($config['action_plan'] ?? []), 'action'),
        ];
    }

    /**
     * The points a question earns: the sum of its selected numeric values (a range: its number, kept within its
     * bounds). A skipped or unanswered question earns 0.
     *
     * @param array<string, mixed> $question
     */
    public static function questionScore(array $question): float
    {
        $control = Questions::control($question);
        if (null === $control || true === ($control['skipped'] ?? false)) {
            return 0.0;
        }
        $type = ControlType::tryFrom((string) ($control['type'] ?? ''));
        $value = $control['value'] ?? null;
        $values = \is_array($value) ? $value : [$value];

        if (ControlType::Range === $type) {
            $number = Scoring::number($values[0] ?? null);
            if (null === $number) {
                return 0.0;
            }
            [$min, $max] = Scoring::rangeBounds($control);

            return max(min($number, $max), $min);
        }

        $sum = 0.0;
        foreach ($values as $v) {
            $sum += Scoring::number($v) ?? 0.0;
        }

        return $sum;
    }

    /**
     * The band that contains the score; when several do, the one with the highest min.
     *
     * @param list<array<string, mixed>> $tiers
     *
     * @return array<string, mixed>|null
     */
    public static function tierFor(float|int $score, array $tiers): ?array
    {
        $found = null;
        foreach ($tiers as $tier) {
            $min = (float) ($tier['min'] ?? 0);
            $max = (float) ($tier['max'] ?? 0);
            if ($score >= $min && $score <= $max && (null === $found || $min > (float) ($found['min'] ?? 0))) {
                $found = $tier;
            }
        }

        return $found;
    }

    /**
     * A diagnostic at the end of a chain scores every stage together (PRD §7.7): the questions of each stage, their
     * ids prefixed by the stage ("s1.", "s2."…) so equal ids of different stages stay apart.
     *
     * @param list<list<array<string, mixed>>> $stages
     *
     * @return list<array<string, mixed>>
     */
    public static function combineStages(array $stages): array
    {
        $combined = [];
        foreach ($stages as $i => $questions) {
            foreach ($questions as $question) {
                $question['id'] = 's'.($i + 1).'.'.($question['id'] ?? '');
                $combined[] = $question;
            }
        }

        return $combined;
    }

    /**
     * The visible texts of the reached tier, or of the nearest lower tier that has any.
     *
     * @param array<string, mixed>|null  $reached
     * @param list<array<string, mixed>> $tiers
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{tier_id: string, recommendation: string}>|list<array{tier_id: string, action: string}>
     */
    private static function textsFor(float|int $score, ?array $reached, array $tiers, array $rows, string $field): array
    {
        if (null !== $reached) {
            $reachedMin = (float) ($reached['min'] ?? 0);
            $candidates = array_filter($tiers, static fn (array $t): bool => $t === $reached || (float) ($t['min'] ?? 0) < $reachedMin);
        } else {
            $candidates = array_filter($tiers, static fn (array $t): bool => (float) ($t['max'] ?? 0) < $score);
        }
        usort($candidates, static fn (array $a, array $b): int => (float) ($b['min'] ?? 0) <=> (float) ($a['min'] ?? 0));
        if (null !== $reached) {
            // The reached tier first, even when a lower one shares its min.
            usort($candidates, static fn (array $a, array $b): int => ($b === $reached) <=> ($a === $reached));
        }

        foreach ($candidates as $tier) {
            $tierId = (string) ($tier['id'] ?? '');
            $texts = [];
            foreach ($rows as $row) {
                if ((string) ($row['tier_id'] ?? '') === $tierId && false !== ($row['visible'] ?? true) && '' !== trim((string) ($row[$field] ?? ''))) {
                    $texts[] = ['tier_id' => $tierId, $field => (string) $row[$field]];
                }
            }
            if ([] !== $texts) {
                return $texts;
            }
        }

        return [];
    }

    /** @return list<array<string, mixed>> */
    private static function rows(mixed $rows): array
    {
        return \is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }
}
