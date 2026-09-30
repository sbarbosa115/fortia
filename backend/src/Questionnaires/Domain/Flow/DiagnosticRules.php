<?php

namespace App\Questionnaires\Domain\Flow;

use App\Shared\Domain\Document\Scoring;

/**
 * "The diagnostic MUST be scorable" (PRD §7.5): at least one tier, unique tier ids, min ≤ max, recommendations and
 * actions that reference existing tiers, a maximum score > 0, and sorted tiers that start at 0, end exactly at the
 * maximum and are contiguous (next.min = previous.max + 1).
 */
final class DiagnosticRules
{
    /**
     * The maximum score of a diagnostic: the questions that are scored (with a category, §7.7), each contributing
     * its own maximum (§7.5), rounded half-up like the total a respondent gets.
     *
     * @param list<array<string, mixed>> $questions
     */
    public static function maxScore(array $questions): int
    {
        $max = 0.0;
        foreach ($questions as $question) {
            $category = $question['category'] ?? null;
            if (\is_string($category) && '' !== trim($category)) {
                $max += Scoring::maxScore($question);
            }
        }

        return Scoring::roundHalfUp($max);
    }

    /**
     * The scoring configuration in its stored shape: tiers {id, name, description, min, max, visible}, recommendations
     * {tier_id, recommendation, visible} and actions {tier_id, action, visible}; visible defaults to true.
     *
     * @param array<string, mixed> $config {tiers?, recommendations?, action_plan?}
     *
     * @return array{tiers: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, action_plan: list<array<string, mixed>>}
     */
    public static function normalize(array $config): array
    {
        $tiers = [];
        foreach (self::objects($config['tiers'] ?? []) as $tier) {
            $tiers[] = [
                'id' => \is_scalar($tier['id'] ?? null) ? (string) $tier['id'] : '',
                'name' => \is_scalar($tier['name'] ?? null) ? (string) $tier['name'] : '',
                'description' => \is_scalar($tier['description'] ?? null) && '' !== (string) $tier['description'] ? (string) $tier['description'] : null,
                'min' => self::score($tier['min'] ?? null),
                'max' => self::score($tier['max'] ?? null),
                'visible' => (bool) ($tier['visible'] ?? true),
            ];
        }

        return [
            'tiers' => $tiers,
            'recommendations' => self::tierTexts($config['recommendations'] ?? [], 'recommendation'),
            'action_plan' => self::tierTexts($config['action_plan'] ?? [], 'action'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $tiers           normalized
     * @param list<array<string, mixed>> $recommendations normalized
     * @param list<array<string, mixed>> $actionPlan      normalized
     *
     * @return list<array{field: string, message: string}>
     */
    public static function violations(array $tiers, array $recommendations, array $actionPlan, int|float $maxScore): array
    {
        $violations = [];
        $add = static function (string $field, string $message) use (&$violations): void {
            $violations[] = ['field' => $field, 'message' => $message];
        };

        if ([] === $tiers) {
            $add('diagnostic.tiers', 'A diagnostic needs at least one tier.');

            return $violations;
        }

        $ids = [];
        foreach ($tiers as $i => $tier) {
            $id = (string) ($tier['id'] ?? '');
            if ('' === $id) {
                $add("diagnostic.tiers[$i].id", 'Every tier needs an id.');
            } elseif (isset($ids[$id])) {
                $add("diagnostic.tiers[$i].id", "Tier ids must be unique (\"$id\" is repeated).");
            }
            $ids[$id] = true;
            if (!self::isNumber($tier['min'] ?? null) || !self::isNumber($tier['max'] ?? null)) {
                $add("diagnostic.tiers[$i]", 'Fill in the score range (from and to) for every tier.');

                return $violations;
            }
            if ($tier['min'] > $tier['max']) {
                $add("diagnostic.tiers[$i]", 'A tier\'s "from" score can\'t be greater than its "to" score.');
            }
        }

        foreach ($recommendations as $i => $recommendation) {
            if (!isset($ids[(string) ($recommendation['tier_id'] ?? '')]) || '' === (string) ($recommendation['tier_id'] ?? '')) {
                $add("diagnostic.recommendations[$i].tier_id", 'Every recommendation must belong to an existing tier.');
            }
        }
        foreach ($actionPlan as $i => $action) {
            if (!isset($ids[(string) ($action['tier_id'] ?? '')]) || '' === (string) ($action['tier_id'] ?? '')) {
                $add("diagnostic.action_plan[$i].tier_id", 'Every action must belong to an existing tier.');
            }
        }

        if ($maxScore <= 0) {
            $add('diagnostic', 'The maximum score must be greater than 0: add scored questions with a category.');

            return $violations;
        }

        $sorted = $tiers;
        usort($sorted, static fn (array $a, array $b): int => $a['min'] <=> $b['min'] ?: $a['max'] <=> $b['max']);
        if (!self::same($sorted[0]['min'], 0)) {
            $add('diagnostic.tiers', 'Your first tier must start at 0.');
        }
        $last = $sorted[\count($sorted) - 1];
        if (!self::same($last['max'], $maxScore)) {
            $add('diagnostic.tiers', \sprintf('Your tiers must reach the maximum score of %s exactly.', self::format($maxScore)));
        }
        for ($i = 1, $n = \count($sorted); $i < $n; ++$i) {
            if (!self::same($sorted[$i]['min'], $sorted[$i - 1]['max'] + 1)) {
                $add('diagnostic.tiers', 'Tiers can\'t leave gaps or overlap: each one must start right after the previous.');
                break;
            }
        }

        return $violations;
    }

    /** @return list<array<string, mixed>> */
    private static function objects(mixed $value): array
    {
        return \is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /** @return list<array<string, mixed>> */
    private static function tierTexts(mixed $value, string $textKey): array
    {
        $out = [];
        foreach (self::objects($value) as $item) {
            $out[] = [
                'tier_id' => \is_scalar($item['tier_id'] ?? null) ? (string) $item['tier_id'] : '',
                $textKey => \is_scalar($item[$textKey] ?? null) ? (string) $item[$textKey] : '',
                'visible' => (bool) ($item['visible'] ?? true),
            ];
        }

        return $out;
    }

    private static function score(mixed $value): int|float|null
    {
        $number = Scoring::number($value);
        if (null === $number) {
            return null;
        }

        return floor($number) === $number ? (int) $number : $number;
    }

    private static function isNumber(mixed $value): bool
    {
        return \is_int($value) || \is_float($value);
    }

    private static function same(int|float $a, int|float $b): bool
    {
        return abs($a - $b) < 1e-9;
    }

    private static function format(int|float $value): string
    {
        return floor($value) === (float) $value ? (string) (int) $value : (string) $value;
    }
}
