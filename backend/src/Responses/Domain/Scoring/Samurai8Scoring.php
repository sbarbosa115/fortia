<?php

namespace App\Responses\Domain\Scoring;

use App\Shared\Domain\Document\Scoring;
use App\Shared\Domain\Text;

/**
 * The Samurai8 result [CLIENT-SPECIFIC], applied to the questionnaires and accounts configured for it (PRD §7.7, D8):
 *
 * - 5 dimensions (Contexto, Datos, Automatización, Calidad, Autonomía), each out of 6; a 3-question dimension is
 *   scaled round(sum × 6 / 9);
 * - tiers by the total out of 30: 0–5 Explorador, 6–10 Practicante, 11–15 Estratega, 16–20 Arquitecto, 21–25
 *   Constructor, 26–30 Maestro;
 * - strengths (dimensions ≥ 4), the weakest dimension, quick wins and a 30-day (Explorador) or 90-day roadmap.
 *
 * Questions go to a dimension by their category (its name, ignoring case and accents); without categories, in
 * order: two questions per dimension, the last one takes the rest. Texts are the respondent app's (D23): this
 * returns ids.
 */
final class Samurai8Scoring
{
    public const DIMENSIONS = ['contexto', 'datos', 'automatizacion', 'calidad', 'autonomia'];
    private const ALIASES = [
        'contexto' => 'contexto', 'context' => 'contexto',
        'datos' => 'datos', 'data' => 'datos',
        'automatizacion' => 'automatizacion', 'automation' => 'automatizacion',
        'calidad' => 'calidad', 'quality' => 'calidad',
        'autonomia' => 'autonomia', 'autonomy' => 'autonomia',
    ];
    /** [id, min, max, percentile] — the share of respondents at or above the tier (PRD §9.12). */
    private const TIERS = [
        ['explorador', 0, 5, 100],
        ['practicante', 6, 10, 55],
        ['estratega', 11, 15, 18],
        ['arquitecto', 16, 20, 10],
        ['constructor', 21, 25, 3],
        ['maestro', 26, 30, 1],
    ];

    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return array{type: string, score: array{value: int, max: int}, tier: array{id: string, min: int, max: int, percentile: int}, dimensions: list<array{id: string, score: int, max: int}>, strengths: list<string>, weakest: string, quick_wins: list<string>, roadmap_days: int}
     */
    public static function score(array $questions): array
    {
        $groups = self::groups($questions);
        $dimensions = [];
        foreach (self::DIMENSIONS as $id) {
            $group = $groups[$id] ?? [];
            $sum = array_sum(array_map(DiagnosticScoring::questionScore(...), $group));
            $score = 3 === \count($group) ? Scoring::roundHalfUp($sum * 6 / 9) : Scoring::roundHalfUp($sum);
            $dimensions[] = ['id' => $id, 'score' => max(0, min(6, $score)), 'max' => 6];
        }
        $total = array_sum(array_column($dimensions, 'score'));
        $tier = self::tierFor($total);

        $ascending = $dimensions;
        usort($ascending, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        return [
            'type' => 'samurai8',
            'score' => ['value' => $total, 'max' => 30],
            'tier' => $tier,
            'dimensions' => $dimensions,
            'strengths' => array_column(array_filter($dimensions, static fn (array $d): bool => $d['score'] >= 4), 'id'),
            'weakest' => $ascending[0]['id'],
            'quick_wins' => array_column(\array_slice($ascending, 0, 2), 'id'),
            'roadmap_days' => 'explorador' === $tier['id'] ? 30 : 90,
        ];
    }

    /** @return array{id: string, min: int, max: int, percentile: int} */
    public static function tierFor(int $score): array
    {
        foreach (self::TIERS as [$id, $min, $max, $percentile]) {
            if ($score >= $min && $score <= $max) {
                return ['id' => $id, 'min' => $min, 'max' => $max, 'percentile' => $percentile];
            }
        }
        [$id, $min, $max, $percentile] = $score < 0 ? self::TIERS[0] : self::TIERS[\count(self::TIERS) - 1];

        return ['id' => $id, 'min' => $min, 'max' => $max, 'percentile' => $percentile];
    }

    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function groups(array $questions): array
    {
        $scored = array_values(array_filter($questions, static fn (array $q): bool => Scoring::maxScore($q) > 0));
        $hasCategories = [] !== array_filter($scored, static fn (array $q): bool => '' !== trim((string) ($q['category'] ?? '')));

        $groups = [];
        if (!$hasCategories) {
            foreach ($scored as $i => $question) {
                $groups[self::DIMENSIONS[min(intdiv($i, 2), 4)]][] = $question;
            }

            return $groups;
        }

        $unknown = [];
        foreach ($scored as $question) {
            $name = str_replace(' ', '', Text::fold((string) ($question['category'] ?? '')));
            $id = self::ALIASES[$name] ?? null;
            if (null !== $id) {
                $groups[$id][] = $question;
            } elseif ('' !== $name) {
                $unknown[$name][] = $question;
            }
        }
        // Categories with other names fill the dimensions still empty, in order.
        $free = array_values(array_diff(self::DIMENSIONS, array_keys($groups)));
        foreach (array_values($unknown) as $i => $group) {
            if (isset($free[$i])) {
                $groups[$free[$i]] = $group;
            }
        }

        return $groups;
    }
}
