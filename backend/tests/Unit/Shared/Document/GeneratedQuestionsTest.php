<?php

namespace App\Tests\Unit\Shared\Document;

use App\Shared\Domain\Document\GeneratedQuestions;
use App\Shared\Domain\Error\UpstreamFailed;
use App\Shared\Domain\Ids;
use PHPUnit\Framework\TestCase;

final class GeneratedQuestionsTest extends TestCase
{
    public function testKeepsOnlyTheFirstControlAndDropsQuestionsWithoutOne(): void
    {
        $clean = GeneratedQuestions::clean([
            ['id' => 'x', 'order' => 5, 'title' => 'One', 'options' => [['type' => 'radio', 'options' => [['label' => 'A']]], ['type' => 'text']]],
            ['id' => 'y', 'title' => 'No control', 'options' => []],
            ['id' => 'z', 'order' => 9, 'title' => 'Two', 'options' => [['type' => 'text', 'value' => 'leaked answer']]],
        ]);

        self::assertCount(2, $clean, 'PRD §7.6: a question without a control is removed');
        self::assertCount(1, $clean[0]['options'], 'PRD §7.6: only the first control is kept');
        self::assertSame([0, 1], array_column($clean, 'order'), 'PRD §7.6: order is renumbered from 0');
        self::assertArrayNotHasKey('value', $clean[1]['options'][0], 'PRD §7.6: runtime fields the LLM filled in are cleared');
    }

    public function testGivesEveryQuestionAndControlANewUuid(): void
    {
        $clean = GeneratedQuestions::clean([['id' => 'same', 'title' => 'T', 'options' => [['name' => 'same', 'type' => 'text']]]]);

        self::assertTrue(Ids::isUuid4($clean[0]['id']));
        self::assertTrue(Ids::isUuid4($clean[0]['options'][0]['name']));
    }

    public function testDeduplicatesOptionValues(): void
    {
        $clean = GeneratedQuestions::clean([['title' => 'T', 'options' => [['type' => 'radio', 'options' => [['label' => 'A', 'value' => 1], ['label' => 'B', 'value' => 1]]]]]]);

        self::assertSame([1, 2], array_column($clean[0]['options'][0]['options'], 'value'));
    }

    public function testFailsWhenNoQuestionIsLeft(): void
    {
        $this->expectException(UpstreamFailed::class);

        GeneratedQuestions::clean([['title' => 'Nothing', 'options' => []]]);
    }
}
