<?php

namespace App\Tests\Unit\Responses;

use App\Responses\Domain\Scoring\DiagnosticScoring;
use PHPUnit\Framework\TestCase;

/** PRD §7.7 "Diagnostic" and §16.3 #4: a session's score matches the rules exactly. */
final class DiagnosticScoringTest extends TestCase
{
    public function testARadioQuestionScoresTheValueOfTheSelectedOption(): void
    {
        $result = DiagnosticScoring::score([self::radio('q1', 'People', ['0', '5', '10'], '5')], self::config());

        self::assertSame(['value' => 5, 'max' => 10.0], $result['score'], '§7.7: category score = sum of the selected values');
        self::assertSame([['id' => 'People', 'name' => 'People', 'score' => 5.0, 'max' => 10.0]], $result['categories']);
    }

    public function testACheckboxScoresTheSumOfEverySelectedValue(): void
    {
        $question = self::control('q1', 'Tools', 'checkbox', ['1', '2', '4'], ['1', '4']);

        $result = DiagnosticScoring::score([$question], self::config());

        self::assertSame(5, $result['score']['value'], 'a checkbox adds every selected value');
        self::assertSame(7.0, $result['score']['max'], '§7.5: a checkbox is worth the sum of its option values');
    }

    public function testARankingScoresTheSumOfItsRankedValues(): void
    {
        $question = self::control('q1', 'Priorities', 'ranking', ['1', '2', '3'], ['3', '1', '2']);

        $result = DiagnosticScoring::score([$question], self::config());

        self::assertSame(6, $result['score']['value']);
        self::assertSame(6.0, $result['score']['max']);
    }

    public function testARangeScoresItsNumberWithinItsBounds(): void
    {
        $range = [
            'id' => 'q1', 'category' => 'Energy',
            'options' => [['name' => 'c', 'type' => 'range', 'validations' => [['type' => 'min', 'value' => 0], ['type' => 'max', 'value' => 7]], 'value' => '6']],
        ];
        $tooHigh = $range;
        $tooHigh['id'] = 'q2';
        $tooHigh['options'][0]['value'] = '99';

        $result = DiagnosticScoring::score([$range, $tooHigh], self::config());

        self::assertSame(13, $result['score']['value'], 'a range counts its value, never above its "max" validation');
        self::assertSame(14.0, $result['score']['max']);
    }

    public function testQuestionsWithoutACategoryOrWithoutAMaximumDoNotCount(): void
    {
        $questions = [
            self::radio('q1', null, ['0', '5'], '5'),
            self::radio('q2', 'People', ['a', 'b'], 'a'),
            ['id' => 'q3', 'category' => 'People', 'options' => [['name' => 'c', 'type' => 'text', 'value' => 'hello']]],
            self::radio('q4', 'People', ['1', '2'], '2'),
        ];

        $result = DiagnosticScoring::score($questions, self::config());

        self::assertSame(['value' => 2, 'max' => 2.0], $result['score'], '§7.7: only questions with a category and a maximum > 0 count');
        self::assertCount(1, $result['categories']);
    }

    public function testCategoriesAddUpInTheOrderTheyFirstAppear(): void
    {
        $questions = [
            self::radio('q1', 'Strategy', ['0', '3'], '3'),
            self::radio('q2', 'People', ['0', '4'], '0'),
            self::radio('q3', 'Strategy', ['0', '3'], '3'),
        ];

        $result = DiagnosticScoring::score($questions, self::config());

        self::assertSame(['Strategy', 'People'], array_column($result['categories'], 'id'));
        self::assertSame([6.0, 0.0], array_column($result['categories'], 'score'));
        self::assertSame(['value' => 6, 'max' => 10.0], $result['score'], 'the total is the sum of the categories');
    }

    public function testTheTotalIsRoundedHalfUp(): void
    {
        $questions = [self::radio('q1', 'A', ['0', '2.5'], '2.5'), self::radio('q2', 'B', ['0', '2.4'], '2.4')];
        self::assertSame(5, DiagnosticScoring::score($questions, self::config())['score']['value'], '4.9 rounds to 5');

        $half = [self::radio('q1', 'A', ['0', '0.5'], '0.5')];
        self::assertSame(1, DiagnosticScoring::score($half, self::config())['score']['value'], '§7.7: 0.5 rounds half-up to 1');
    }

    public function testASkippedOrUnansweredQuestionScoresZeroButKeepsItsMaximum(): void
    {
        $skipped = self::radio('q1', 'A', ['0', '5'], null);
        $skipped['options'][0]['skipped'] = true;

        $result = DiagnosticScoring::score([$skipped], self::config());

        self::assertSame(['value' => 0, 'max' => 5.0], $result['score']);
    }

    public function testTheTierIsTheBandThatContainsTheTotalIncludingItsBoundaries(): void
    {
        $tiers = [self::tier('low', 0, 5), self::tier('mid', 6, 10), self::tier('high', 11, 20)];

        self::assertSame('low', DiagnosticScoring::tierFor(5, $tiers)['id'] ?? null, 'the max of a band is inside it');
        self::assertSame('mid', DiagnosticScoring::tierFor(6, $tiers)['id'] ?? null, 'the min of a band is inside it');
        self::assertSame('high', DiagnosticScoring::tierFor(20, $tiers)['id'] ?? null);
        self::assertNull(DiagnosticScoring::tierFor(21, $tiers), 'no band contains 21');
    }

    public function testWhenBandsOverlapTheOneWithTheHighestMinWins(): void
    {
        $tiers = [self::tier('wide', 0, 10), self::tier('narrow', 5, 10)];

        self::assertSame('narrow', DiagnosticScoring::tierFor(7, $tiers)['id'] ?? null, '§9.12: if they overlap, the highest min wins');
    }

    public function testTheResultShowsOnlyVisibleTiersAndTheTextsOfTheReachedTier(): void
    {
        $config = [
            'tiers' => [self::tier('low', 0, 4), self::tier('high', 5, 10, visible: false)],
            'recommendations' => [
                ['tier_id' => 'low', 'recommendation' => 'Start small', 'visible' => true],
                ['tier_id' => 'high', 'recommendation' => 'Keep going', 'visible' => true],
                ['tier_id' => 'high', 'recommendation' => 'Hidden one', 'visible' => false],
            ],
            'action_plan' => [['tier_id' => 'high', 'action' => 'Scale it', 'visible' => true]],
        ];

        $result = DiagnosticScoring::score([self::radio('q1', 'A', ['0', '8'], '8')], $config);

        self::assertSame(['low'], array_column($result['tiers'], 'id'), '§6.10: tiers: visible ones only');
        self::assertSame([['tier_id' => 'high', 'recommendation' => 'Keep going']], $result['recommendations'], 'the reached tier\'s visible recommendations');
        self::assertSame([['tier_id' => 'high', 'action' => 'Scale it']], $result['action_plan']);
        self::assertSame('diagnostic', $result['type']);
    }

    public function testATierWithoutRecommendationsInheritsThemFromTheNearestLowerTierNeverAHigherOne(): void
    {
        $config = [
            'tiers' => [self::tier('t1', 0, 3), self::tier('t2', 4, 6), self::tier('t3', 7, 9), self::tier('t4', 10, 12)],
            'recommendations' => [
                ['tier_id' => 't1', 'recommendation' => 'From t1', 'visible' => true],
                ['tier_id' => 't2', 'recommendation' => 'From t2', 'visible' => true],
                ['tier_id' => 't4', 'recommendation' => 'From t4', 'visible' => true],
            ],
            'action_plan' => [['tier_id' => 't1', 'action' => 'Act t1', 'visible' => true]],
        ];

        $result = DiagnosticScoring::score([self::radio('q1', 'A', ['0', '8', '12'], '8')], $config);

        self::assertSame([['tier_id' => 't2', 'recommendation' => 'From t2']], $result['recommendations'], '§9.12: t3 has none, so the nearest lower tier (t2) gives them, never t4');
        self::assertSame([['tier_id' => 't1', 'action' => 'Act t1']], $result['action_plan'], 'the action plan inherits on its own');
    }

    public function testAScoreOutsideEveryBandTakesTheTextsOfTheBandBelowIt(): void
    {
        $config = [
            'tiers' => [self::tier('t1', 0, 3)],
            'recommendations' => [['tier_id' => 't1', 'recommendation' => 'From t1', 'visible' => true]],
            'action_plan' => [],
        ];

        $result = DiagnosticScoring::score([self::radio('q1', 'A', ['0', '5'], '5')], $config);

        self::assertSame([['tier_id' => 't1', 'recommendation' => 'From t1']], $result['recommendations']);
    }

    public function testAChainScoresEveryStageTogetherWithQuestionIdsPrefixedByStage(): void
    {
        $stage1 = [self::radio('same-id', 'Strategy', ['0', '4'], '4')];
        $stage2 = [self::radio('same-id', 'Strategy', ['0', '4'], '2'), self::radio('q9', 'People', ['0', '5'], '5')];

        $combined = DiagnosticScoring::combineStages([$stage1, $stage2]);
        $result = DiagnosticScoring::score($combined, self::config());

        self::assertSame(['s1.same-id', 's2.same-id', 's2.q9'], array_column($combined, 'id'), '§7.7: question ids prefixed by stage');
        self::assertSame(['value' => 11, 'max' => 13.0], $result['score'], '§16.3 #5: the combined score across all stages');
        self::assertSame([6.0, 5.0], array_column($result['categories'], 'score'));
    }

    /** @return array{tiers: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, action_plan: list<array<string, mixed>>} */
    private static function config(): array
    {
        return ['tiers' => [], 'recommendations' => [], 'action_plan' => []];
    }

    /** @return array<string, mixed> */
    private static function tier(string $id, float $min, float $max, bool $visible = true): array
    {
        return ['id' => $id, 'name' => ucfirst($id), 'description' => null, 'min' => $min, 'max' => $max, 'visible' => $visible];
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, mixed>
     */
    private static function radio(string $id, ?string $category, array $values, ?string $selected): array
    {
        return self::control($id, $category, 'radio', $values, $selected);
    }

    /**
     * @param list<string>             $values
     * @param string|list<string>|null $selected
     *
     * @return array<string, mixed>
     */
    private static function control(string $id, ?string $category, string $type, array $values, string|array|null $selected): array
    {
        return [
            'id' => $id,
            'category' => $category,
            'options' => [[
                'name' => $id.'-control',
                'type' => $type,
                'options' => array_map(static fn (string $v): array => ['label' => 'Option '.$v, 'value' => $v], $values),
                'value' => $selected,
            ]],
        ];
    }
}
