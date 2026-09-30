<?php

namespace App\Tests\Unit\Questionnaires;

use App\Questionnaires\Domain\Flow\DiagnosticRules;
use PHPUnit\Framework\TestCase;

/** PRD §7.5 "The diagnostic MUST be scorable". */
final class DiagnosticRulesTest extends TestCase
{
    public function testContiguousTiersFromZeroToTheMaximumAreScorable(): void
    {
        self::assertSame([], DiagnosticRules::violations(self::tiers([0, 4], [5, 9], [10, 12]), [], [], 12));
    }

    public function testTiersMayArriveInAnyOrder(): void
    {
        self::assertSame([], DiagnosticRules::violations(self::tiers([5, 12], [0, 4]), [], [], 12), 'the rules apply to the sorted tiers');
    }

    public function testAtLeastOneTier(): void
    {
        self::assertMessage('at least one tier', DiagnosticRules::violations([], [], [], 10));
    }

    public function testTierIdsAreUnique(): void
    {
        $tiers = self::tiers([0, 4], [5, 10]);
        $tiers[1]['id'] = $tiers[0]['id'];

        self::assertMessage('unique', DiagnosticRules::violations($tiers, [], [], 10));
    }

    public function testMinIsNotGreaterThanMax(): void
    {
        self::assertMessage('greater', DiagnosticRules::violations(self::tiers([0, 6], [8, 7]), [], [], 7));
    }

    public function testTheMaximumScoreMustBePositive(): void
    {
        self::assertMessage('maximum score', DiagnosticRules::violations(self::tiers([0, 0]), [], [], 0));
    }

    public function testTheFirstTierStartsAtZero(): void
    {
        self::assertMessage('start at 0', DiagnosticRules::violations(self::tiers([1, 10]), [], [], 10));
    }

    public function testTheLastTierEndsExactlyAtTheMaximum(): void
    {
        self::assertMessage('reach the maximum', DiagnosticRules::violations(self::tiers([0, 4], [5, 9]), [], [], 10));
        self::assertMessage('reach the maximum', DiagnosticRules::violations(self::tiers([0, 4], [5, 11]), [], [], 10));
    }

    public function testTiersLeaveNoGapsAndDoNotOverlap(): void
    {
        self::assertMessage('gaps', DiagnosticRules::violations(self::tiers([0, 4], [6, 10]), [], [], 10));
        self::assertMessage('gaps', DiagnosticRules::violations(self::tiers([0, 5], [5, 10]), [], [], 10));
    }

    public function testRecommendationsAndActionsReferenceExistingTiers(): void
    {
        $tiers = self::tiers([0, 10]);

        self::assertMessage('recommendation', DiagnosticRules::violations($tiers, [['tier_id' => 'ghost', 'recommendation' => 'x', 'visible' => true]], [], 10));
        self::assertMessage('action', DiagnosticRules::violations($tiers, [], [['tier_id' => 'ghost', 'action' => 'x', 'visible' => true]], 10));
    }

    public function testTheMaximumScoreSumsTheCategorisedQuestions(): void
    {
        $questions = [
            ['category' => 'A', 'options' => [['type' => 'checkbox', 'options' => [['value' => 1], ['value' => 2]]]]],
            ['category' => 'A', 'options' => [['type' => 'radio', 'options' => [['value' => 1], ['value' => 4]]]]],
            ['category' => 'B', 'options' => [['type' => 'range', 'validations' => [['type' => 'min', 'value' => 0], ['type' => 'max', 'value' => 5]]]]],
            ['category' => null, 'options' => [['type' => 'radio', 'options' => [['value' => 100]]]]],
        ];

        self::assertSame(12, DiagnosticRules::maxScore($questions), 'PRD §7.5/§7.7: checkbox sums (3), radio takes its highest (4), range its max (5); uncategorised questions do not count');
    }

    public function testNormalizeFillsTheDefaults(): void
    {
        $normalized = DiagnosticRules::normalize(['tiers' => [['id' => 't1', 'name' => 'All', 'min' => '0', 'max' => '10']], 'recommendations' => [['tier_id' => 't1', 'recommendation' => 'Go']]]);

        self::assertSame([['id' => 't1', 'name' => 'All', 'description' => null, 'min' => 0, 'max' => 10, 'visible' => true]], $normalized['tiers']);
        self::assertSame([['tier_id' => 't1', 'recommendation' => 'Go', 'visible' => true]], $normalized['recommendations']);
        self::assertSame([], $normalized['action_plan']);
    }

    /** @return list<array<string, mixed>> */
    private static function tiers(array ...$ranges): array
    {
        $tiers = [];
        foreach ($ranges as $i => [$min, $max]) {
            $tiers[] = ['id' => 't'.$i, 'name' => 'Tier '.$i, 'description' => null, 'min' => $min, 'max' => $max, 'visible' => true];
        }

        return $tiers;
    }

    /** @param list<array{field: string, message: string}> $violations */
    private static function assertMessage(string $fragment, array $violations): void
    {
        $messages = implode(' | ', array_column($violations, 'message'));
        self::assertStringContainsStringIgnoringCase($fragment, $messages, 'PRD §7.5 names this rule');
    }
}
