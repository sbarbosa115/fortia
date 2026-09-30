<?php

namespace App\Responses\Domain\Scoring;

use App\Shared\Domain\Document\Scoring;
use App\Shared\Domain\Text;

/**
 * The Livingood result [CLIENT-SPECIFIC], for the accounts configured for it (PRD §7.7, D8): four scores out of 100
 * — Fat Loss, Gut Health, Hormone Balance, Energy & Vitality — from the questions of each area (by category), plus
 * a profile (the area that needs the most work) and an action plan (the areas under 70, weakest first). Texts are
 * the respondent app's (D23): this returns ids.
 */
final class LivingoodScoring
{
    public const AREAS = ['fat_loss', 'gut_health', 'hormone_balance', 'energy_vitality'];
    private const ACTION_THRESHOLD = 70;

    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return array{type: string, scores: list<array{id: string, score: int, max: int}>, profile: string, action_plan: list<string>}
     */
    public static function score(array $questions): array
    {
        $sums = array_fill_keys(self::AREAS, 0.0);
        $maxes = array_fill_keys(self::AREAS, 0.0);
        foreach ($questions as $question) {
            $area = self::areaOf((string) ($question['category'] ?? ''));
            $max = Scoring::maxScore($question);
            if (null === $area || $max <= 0) {
                continue;
            }
            $sums[$area] += DiagnosticScoring::questionScore($question);
            $maxes[$area] += $max;
        }

        $scores = [];
        foreach (self::AREAS as $area) {
            $score = $maxes[$area] > 0 ? Scoring::roundHalfUp($sums[$area] / $maxes[$area] * 100) : 0;
            $scores[] = ['id' => $area, 'score' => max(0, min(100, $score)), 'max' => 100];
        }
        $ascending = $scores;
        usort($ascending, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        return [
            'type' => 'livingood',
            'scores' => $scores,
            'profile' => $ascending[0]['id'],
            'action_plan' => array_values(array_column(array_filter($ascending, static fn (array $s): bool => $s['score'] < self::ACTION_THRESHOLD), 'id')),
        ];
    }

    private static function areaOf(string $category): ?string
    {
        $name = Text::fold($category);
        if ('' === $name) {
            return null;
        }
        $name = (string) preg_replace('/[^a-z]+/', '_', $name);

        return match (true) {
            str_starts_with($name, 'fat') => 'fat_loss',
            str_starts_with($name, 'gut') => 'gut_health',
            str_starts_with($name, 'hormone') => 'hormone_balance',
            str_starts_with($name, 'energy') => 'energy_vitality',
            default => null,
        };
    }
}
