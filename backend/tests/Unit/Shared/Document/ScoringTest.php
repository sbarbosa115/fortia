<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Document;

use App\Shared\Domain\Document\Scoring;
use PHPUnit\Framework\TestCase;

final class ScoringTest extends TestCase
{
    public function testACheckboxContributesTheSumOfItsOptionValues(): void
    {
        $question = ['options' => [$this->control('checkbox', [1, 2, 3])]];

        self::assertSame(6.0, Scoring::maxScore($question), 'PRD §7.5: checkbox and ranking add up all their values');
    }

    public function testARankingContributesTheSumOfItsOptionValues(): void
    {
        self::assertSame(10.0, Scoring::maxScore(['options' => [$this->control('ranking', [1, 2, 3, 4])]]));
    }

    public function testARadioContributesItsHighestValue(): void
    {
        self::assertSame(4.0, Scoring::maxScore(['options' => [$this->control('radio', [0, 4, 2])]]), 'PRD §7.5: any other control contributes its highest value');
    }

    public function testARangeContributesItsMaxValidation(): void
    {
        $range = ['type' => 'range', 'options' => [], 'validations' => [['type' => 'min', 'value' => 1], ['type' => 'max', 'value' => 7]]];

        self::assertSame(7.0, Scoring::maxScore(['options' => [$range]]));
        self::assertSame([1.0, 7.0], Scoring::rangeBounds($range));
    }

    public function testARangeWithoutValidationsGoesFromZeroToTen(): void
    {
        self::assertSame([0.0, 10.0], Scoring::rangeBounds(['type' => 'range']), 'PRD §9.4: defaults are 0 and 10');
    }

    public function testTextOptionValuesDoNotScore(): void
    {
        self::assertSame(0.0, Scoring::maxScore(['options' => [$this->control('radio', ['yes', 'no'])]]));
    }

    public function testAQuestionAddsUpAllItsControls(): void
    {
        $question = ['options' => [$this->control('radio', [1, 3]), $this->control('checkbox', [2, 2])]];

        self::assertSame(7.0, Scoring::maxScore($question));
    }

    public function testRoundsHalfUp(): void
    {
        self::assertSame(3, Scoring::roundHalfUp(2.5), 'PRD §7.7: the total is rounded half-up');
        self::assertSame(2, Scoring::roundHalfUp(2.49));
    }

    /**
     * @param list<int|string> $values
     *
     * @return array<string, mixed>
     */
    private function control(string $type, array $values): array
    {
        return ['type' => $type, 'options' => array_map(static fn ($v): array => ['label' => (string) $v, 'value' => $v], $values)];
    }
}
