<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Document;

use App\Shared\Domain\Document\OptionValues;
use PHPUnit\Framework\TestCase;

final class OptionValuesTest extends TestCase
{
    public function testARepeatedNumberIsBumpedAboveTheHighestInUse(): void
    {
        $options = OptionValues::dedupe([['label' => 'a', 'value' => 1], ['label' => 'b', 'value' => 3], ['label' => 'c', 'value' => 1]]);

        self::assertSame([1, 3, 4], array_column($options, 'value'), 'PRD §7.5: a repeated number goes above the highest one');
    }

    public function testARepeatedTextGetsANumberedSuffix(): void
    {
        $options = OptionValues::dedupe([['label' => 'a', 'value' => 'yes'], ['label' => 'b', 'value' => 'yes'], ['label' => 'c', 'value' => 'yes']]);

        self::assertSame(['yes', 'yes-2', 'yes-3'], array_column($options, 'value'));
    }

    public function testOptionsWithoutValueAreLeftAlone(): void
    {
        $options = OptionValues::dedupe([['label' => 'a', 'value' => null], ['label' => 'b', 'value' => null]]);

        self::assertSame([null, null], array_column($options, 'value'), 'a null value falls back to its label, so it is not a duplicate');
    }
}
