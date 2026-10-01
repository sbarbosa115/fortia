<?php

namespace App\Tests\Unit\Generation;

use App\Generation\Domain\TierBands;
use PHPUnit\Framework\TestCase;

final class TierBandsTest extends TestCase
{
    public function testTheBandsStartAtZeroAreContiguousAndEndAtTheMaximum(): void
    {
        $bands = TierBands::compute(3, 14);

        self::assertSame([[0, 4], [5, 9], [10, 14]], $bands, '§7.5: sorted tiers start at 0, end at the maximum and next.min = previous.max + 1');
    }

    public function testAnUnevenRangeStillCoversEveryScore(): void
    {
        $bands = TierBands::compute(4, 10);

        self::assertSame(0, $bands[0][0]);
        self::assertSame(10, $bands[3][1], 'the last tier ends exactly at the maximum');
        for ($i = 1; $i < 4; ++$i) {
            self::assertSame($bands[$i - 1][1] + 1, $bands[$i][0], 'contiguous: no gaps, no overlaps');
            self::assertLessThanOrEqual($bands[$i][1], $bands[$i][0], 'min ≤ max');
        }
    }

    public function testMoreTiersThanScoresKeepsOnlyAsManyAsThereAreScores(): void
    {
        self::assertSame([[0, 0], [1, 1], [2, 2]], TierBands::compute(5, 2), 'a band cannot be empty: 0..2 fits three tiers at most');
    }

    public function testTheGeneratedTiersGetTheirBandsAndTheirTexts(): void
    {
        $diagnostic = TierBands::diagnostic([
            ['name' => 'Starting', 'description' => 'Early days', 'recommendations' => ['Pick one goal'], 'action_plan' => ['Write it down']],
            ['name' => 'Leading', 'description' => 'Ahead', 'recommendations' => ['Share it', 'Teach it'], 'action_plan' => []],
        ], 9);

        self::assertSame(['id' => 'tier-1', 'name' => 'Starting', 'description' => 'Early days', 'min' => 0, 'max' => 4, 'visible' => true], $diagnostic['tiers'][0]);
        self::assertSame(['id' => 'tier-2', 'name' => 'Leading', 'description' => 'Ahead', 'min' => 5, 'max' => 9, 'visible' => true], $diagnostic['tiers'][1]);
        self::assertSame([
            ['tier_id' => 'tier-1', 'recommendation' => 'Pick one goal', 'visible' => true],
            ['tier_id' => 'tier-2', 'recommendation' => 'Share it', 'visible' => true],
            ['tier_id' => 'tier-2', 'recommendation' => 'Teach it', 'visible' => true],
        ], $diagnostic['recommendations']);
        self::assertSame([['tier_id' => 'tier-1', 'action' => 'Write it down', 'visible' => true]], $diagnostic['action_plan']);
    }

    public function testATierWithoutRecommendationsIsNotUsable(): void
    {
        self::assertFalse(TierBands::usable([['name' => 'A', 'recommendations' => ['x']], ['name' => 'B', 'recommendations' => ['  ']]]), '§7.8: a retry happens if any tier comes without recommendations');
        self::assertFalse(TierBands::usable([]), 'no tiers at all is an empty result');
        self::assertTrue(TierBands::usable([['name' => 'A', 'recommendations' => ['x']]]));
    }
}
