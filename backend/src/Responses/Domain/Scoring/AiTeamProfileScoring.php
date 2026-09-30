<?php

namespace App\Responses\Domain\Scoring;

use App\Shared\Domain\Document\ControlType;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Document\Scoring;

/**
 * The AI Team Profile result (PRD §7.7, §9.12): fixed scoring over the first 8 radio questions, mapped to 5
 * dimensions (D1–D5) out of 6 each; the total (out of 30) places the team in one of 5 stages, from "No usage" to
 * "Transformation". The report adds reference values (average and top 10 %), a percentile, the potential left,
 * the strengths (dimensions ≥ 4), the biggest opportunity and a 90-day roadmap. Texts are the respondent app's
 * (D23): this returns ids.
 */
final class AiTeamProfileScoring
{
    /** Which dimension each of the 8 questions feeds. */
    private const MAPPING = ['d1', 'd1', 'd2', 'd2', 'd3', 'd3', 'd4', 'd5'];
    public const DIMENSIONS = ['d1', 'd2', 'd3', 'd4', 'd5'];
    /** [id, the total it starts at, percentile of a team at that stage]. */
    public const STAGES = [
        ['no_usage', 0, 15],
        ['exploration', 6, 35],
        ['adoption', 12, 60],
        ['integration', 18, 85],
        ['transformation', 24, 97],
    ];
    /** Reference profiles per dimension, out of 6. */
    private const AVERAGE = [2.4, 2.1, 1.9, 2.2, 1.8];
    private const TOP10 = [5.2, 4.9, 4.6, 5.0, 4.7];

    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return array<string, mixed>|null null when the questionnaire does not have its 8 radio questions
     */
    public static function score(array $questions): ?array
    {
        $radios = array_values(array_filter($questions, static fn (array $q): bool => ControlType::Radio === Questions::controlType($q)));
        if (\count($radios) < \count(self::MAPPING)) {
            return null;
        }

        $sums = array_fill_keys(self::DIMENSIONS, 0.0);
        $maxes = array_fill_keys(self::DIMENSIONS, 0.0);
        foreach (self::MAPPING as $i => $dimension) {
            $sums[$dimension] += DiagnosticScoring::questionScore($radios[$i]);
            $maxes[$dimension] += Scoring::maxScore($radios[$i]);
        }
        $dimensions = [];
        foreach (self::DIMENSIONS as $dimension) {
            $score = $maxes[$dimension] > 0 ? round($sums[$dimension] / $maxes[$dimension] * 6, 1) : 0.0;
            $dimensions[] = ['id' => $dimension, 'score' => $score, 'max' => 6];
        }
        $total = round(array_sum(array_column($dimensions, 'score')), 1);

        $stage = 0;
        foreach (self::STAGES as $i => [, $from]) {
            if ($total >= $from) {
                $stage = $i;
            }
        }
        $ascending = $dimensions;
        usort($ascending, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        return [
            'type' => 'ai_team_profile',
            'score' => ['value' => $total, 'max' => 30],
            'stage' => ['index' => $stage, 'id' => self::STAGES[$stage][0]],
            'stages' => array_column(self::STAGES, 0),
            'dimensions' => $dimensions,
            'average' => self::AVERAGE,
            'top10' => self::TOP10,
            'percentile' => self::STAGES[$stage][2],
            'potential' => (int) round((30 - $total) / 30 * 100),
            'strengths' => array_values(array_column(array_filter($dimensions, static fn (array $d): bool => $d['score'] >= 4), 'id')),
            'opportunity' => $ascending[0]['id'],
            'roadmap' => [
                ['days' => '1-30', 'dimension' => $ascending[0]['id']],
                ['days' => '31-60', 'dimension' => $ascending[1]['id']],
                ['days' => '61-90', 'dimension' => $ascending[2]['id']],
            ],
        ];
    }
}
