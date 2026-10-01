<?php

namespace App\Tests\Unit\Generation;

use App\Generation\Domain\ModelQuestionnaire;
use App\Shared\Domain\Error\UpstreamFailed;
use PHPUnit\Framework\TestCase;

final class ModelQuestionnaireTest extends TestCase
{
    public function testTheModelsQuestionsBecomeStoredQuestionsWithNewIds(): void
    {
        $questions = ModelQuestionnaire::questions(['questions' => [
            ['title' => 'How mature is your process?', 'description' => '', 'category' => 'Process', 'type' => 'radio', 'choices' => [['label' => 'Low', 'value' => 0], ['label' => 'High', 'value' => 3]]],
            ['title' => 'Anything else?', 'description' => 'Optional', 'category' => 'Ignored', 'type' => 'text', 'choices' => []],
        ]], true);

        self::assertCount(2, $questions);
        self::assertSame(0, $questions[0]['order']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $questions[0]['id'], '§7.6: a new UUID for each question');
        self::assertSame('radio', $questions[0]['options'][0]['type']);
        self::assertSame('Process', $questions[0]['category']);
        self::assertNull($questions[1]['category'], 'a text answer cannot be scored, so it has no category');
        self::assertSame('Optional', $questions[1]['description']);
    }

    public function testAScoredQuestionGetsNumbersWhereTheModelGaveNone(): void
    {
        $questions = ModelQuestionnaire::questions(['questions' => [
            ['title' => 'Q', 'description' => '', 'category' => 'C', 'type' => 'radio', 'choices' => [['label' => 'A', 'value' => null], ['label' => 'B', 'value' => null], ['label' => 'C', 'value' => null]]],
        ]], true);

        self::assertSame([0, 1, 2], array_column($questions[0]['options'][0]['options'], 'value'));
        self::assertSame(2, ModelQuestionnaire::maxScore($questions));
    }

    public function testUnusableQuestionsAreDroppedAndNothingLeftFails(): void
    {
        $this->expectException(UpstreamFailed::class);

        ModelQuestionnaire::questions(['questions' => [
            ['title' => '', 'type' => 'radio', 'choices' => [['label' => 'A'], ['label' => 'B']]],
            ['title' => 'One choice is no choice', 'type' => 'radio', 'choices' => [['label' => 'A']]],
            ['title' => 'Unknown control', 'type' => 'hologram', 'choices' => []],
        ]], false);
    }

    public function testTheMaximumCountsOnlyQuestionsWithACategory(): void
    {
        $questions = ModelQuestionnaire::questions(['questions' => [
            ['title' => 'Scored', 'description' => '', 'category' => 'A', 'type' => 'checkbox', 'choices' => [['label' => 'x', 'value' => 1], ['label' => 'y', 'value' => 2]]],
            ['title' => 'Not scored', 'description' => '', 'category' => '', 'type' => 'radio', 'choices' => [['label' => 'x', 'value' => 5], ['label' => 'y', 'value' => 9]]],
        ]], true);

        self::assertSame(3, ModelQuestionnaire::maxScore($questions), '§7.5: a checkbox contributes the sum of its values; §7.7: only questions with a category count');
    }
}
