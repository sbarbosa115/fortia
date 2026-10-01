<?php

namespace App\Generation\Domain;

/**
 * The tier bands of a generated diagnostic are computed on the server (PRD §7.8): the language model only names the
 * tiers and writes their texts; the bands split 0..maximum evenly, start at 0, are contiguous and end exactly at the
 * maximum, so the diagnostic is scorable (§7.5).
 */
final class TierBands
{
    /**
     * @return list<array{0: int, 1: int}> [min, max] per tier, at most one per possible score
     */
    public static function compute(int $tiers, int $maxScore): array
    {
        $maxScore = max(0, $maxScore);
        $count = max(1, min($tiers, $maxScore + 1));
        $bands = [];
        $min = 0;
        for ($i = 0; $i < $count; ++$i) {
            $max = $i === $count - 1 ? $maxScore : intdiv(($i + 1) * ($maxScore + 1), $count) - 1;
            $bands[] = [$min, $max];
            $min = $max + 1;
        }

        return $bands;
    }

    /**
     * Whether the model's tiers can be used: at least one, and every tier with a recommendation (§7.8: otherwise
     * the generation is retried).
     *
     * @param array<int|string, mixed> $generated
     */
    public static function usable(array $generated): bool
    {
        if ([] === $generated) {
            return false;
        }
        foreach ($generated as $tier) {
            if (!\is_array($tier) || [] === self::texts($tier['recommendations'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The scoring configuration from the model's tiers: {tiers, recommendations, action_plan} with server bands.
     *
     * @param list<array<string, mixed>> $generated [{name, description?, recommendations[], action_plan[]}]
     *
     * @return array{tiers: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, action_plan: list<array<string, mixed>>}
     */
    public static function diagnostic(array $generated, int $maxScore): array
    {
        $bands = self::compute(\count($generated), $maxScore);
        $tiers = [];
        $recommendations = [];
        $actions = [];
        foreach ($bands as $i => [$min, $max]) {
            $tier = $generated[$i];
            $id = 'tier-'.($i + 1);
            $name = trim(\is_scalar($tier['name'] ?? null) ? (string) $tier['name'] : '');
            $description = trim(\is_scalar($tier['description'] ?? null) ? (string) $tier['description'] : '');
            $tiers[] = [
                'id' => $id,
                'name' => '' === $name ? 'Tier '.($i + 1) : $name,
                'description' => '' === $description ? null : $description,
                'min' => $min,
                'max' => $max,
                'visible' => true,
            ];
            foreach (self::texts($tier['recommendations'] ?? null) as $text) {
                $recommendations[] = ['tier_id' => $id, 'recommendation' => $text, 'visible' => true];
            }
            foreach (self::texts($tier['action_plan'] ?? null) as $text) {
                $actions[] = ['tier_id' => $id, 'action' => $text, 'visible' => true];
            }
        }

        return ['tiers' => $tiers, 'recommendations' => $recommendations, 'action_plan' => $actions];
    }

    /** @return list<string> */
    private static function texts(mixed $values): array
    {
        $out = [];
        foreach (\is_array($values) ? $values : [] as $value) {
            if (\is_scalar($value) && '' !== trim((string) $value)) {
                $out[] = trim((string) $value);
            }
        }

        return $out;
    }
}
