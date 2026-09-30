<?php

namespace App\Tests\Unit\Responses;

use App\Responses\Domain\Scoring\AiTeamProfileScoring;
use App\Responses\Domain\Scoring\LivingoodScoring;
use App\Responses\Domain\Scoring\Samurai8Scoring;
use PHPUnit\Framework\TestCase;

/** The fixed result types of PRD §7.7: AI Team Profile, and the configured client results Samurai8 and Livingood (D8). */
final class ProfileResultsTest extends TestCase
{
    public function testSamurai8ScalesAThreeQuestionDimensionToSix(): void
    {
        $questions = [
            ...self::dimension('Contexto', [3, 3]),
            ...self::dimension('Datos', [2, 2]),
            ...self::dimension('Automatización', [1, 0]),
            ...self::dimension('Calidad', [3, 3]),
            ...self::dimension('Autonomía', [3, 2, 2]),
        ];

        $result = Samurai8Scoring::score($questions);

        self::assertSame([6, 4, 1, 6, 5], array_column($result['dimensions'], 'score'), '§7.7: a 3-question dimension is scaled round(sum × 6 / 9): 7 × 6 / 9 = 4.67 → 5');
        self::assertSame(['value' => 22, 'max' => 30], $result['score']);
        self::assertSame('constructor', $result['tier']['id'], '21–25 is Constructor');
        self::assertSame(['contexto', 'datos', 'calidad', 'autonomia'], $result['strengths'], 'strengths: dimensions ≥ 4');
        self::assertSame('automatizacion', $result['weakest']);
        self::assertSame(90, $result['roadmap_days'], 'a 90-day roadmap for every tier but Explorador');
    }

    public function testSamurai8TiersFollowTheirBands(): void
    {
        $cases = [0 => 'explorador', 5 => 'explorador', 6 => 'practicante', 10 => 'practicante', 11 => 'estratega', 16 => 'arquitecto', 21 => 'constructor', 26 => 'maestro', 30 => 'maestro'];
        foreach ($cases as $score => $tier) {
            self::assertSame($tier, Samurai8Scoring::tierFor($score)['id'], "§7.7: $score is $tier");
        }
        self::assertSame(30, Samurai8Scoring::score(self::dimension('Contexto', [0, 0]))['roadmap_days'], 'Explorador gets a 30-day roadmap');
    }

    public function testAiTeamProfileMapsEightRadioQuestionsToFiveDimensionsOutOfSix(): void
    {
        $questions = self::radios([3, 3, 0, 0, 3, 0, 3, 1]);

        $profile = AiTeamProfileScoring::score($questions);

        self::assertNotNull($profile);
        self::assertSame(['d1', 'd2', 'd3', 'd4', 'd5'], array_column($profile['dimensions'], 'id'));
        self::assertSame([6.0, 0.0, 3.0, 6.0, 2.0], array_column($profile['dimensions'], 'score'));
        self::assertSame(['value' => 17.0, 'max' => 30], $profile['score']);
        self::assertSame('adoption', $profile['stage']['id'], '5 stages from "No usage" to "Transformation"');
        self::assertSame(['d1', 'd4'], $profile['strengths'], '§9.12: strengths are dimensions ≥ 4/6');
        self::assertSame('d2', $profile['opportunity']);
        self::assertSame(['1-30', '31-60', '61-90'], array_column($profile['roadmap'], 'days'));
        self::assertCount(5, $profile['average']);
        self::assertCount(5, $profile['top10']);
    }

    public function testAiTeamProfileNeedsItsEightRadioQuestions(): void
    {
        self::assertNull(AiTeamProfileScoring::score(self::radios([3, 3, 3])), 'without its 8 questions there is no report');
    }

    public function testLivingoodScoresFourAreasOutOf100(): void
    {
        $questions = [
            ...self::dimension('Fat Loss', [3, 1]),
            ...self::dimension('Gut Health', [3, 3]),
            ...self::dimension('Hormone Balance', [0, 0]),
            ...self::dimension('Energy & Vitality', [2, 1]),
        ];

        $result = LivingoodScoring::score($questions);

        self::assertSame(['fat_loss', 'gut_health', 'hormone_balance', 'energy_vitality'], array_column($result['scores'], 'id'));
        self::assertSame([67, 100, 0, 50], array_column($result['scores'], 'score'));
        self::assertSame('hormone_balance', $result['profile'], 'the profile is the area that needs the most work');
        self::assertSame(['hormone_balance', 'energy_vitality', 'fat_loss'], $result['action_plan'], 'the action plan starts with the weakest area under 70');
    }

    /**
     * @param list<int> $selected each question's selected value, out of 3
     *
     * @return list<array<string, mixed>>
     */
    private static function dimension(string $category, array $selected): array
    {
        $out = [];
        foreach ($selected as $i => $value) {
            $out[] = self::radio($category.'-'.$i, $category, $value);
        }

        return $out;
    }

    /**
     * @param list<int> $selected
     *
     * @return list<array<string, mixed>>
     */
    private static function radios(array $selected): array
    {
        return array_map(static fn (int $v, int $i): array => self::radio('q'.$i, null, $v), $selected, array_keys($selected));
    }

    /** @return array<string, mixed> */
    private static function radio(string $id, ?string $category, int $selected): array
    {
        return [
            'id' => $id,
            'category' => $category,
            'options' => [[
                'name' => $id.'-c',
                'type' => 'radio',
                'options' => array_map(static fn (int $v): array => ['label' => (string) $v, 'value' => (string) $v], [0, 1, 2, 3]),
                'value' => (string) $selected,
            ]],
        ];
    }
}
