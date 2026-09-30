<?php

namespace App\Tests\Unit\Responses;

use App\Responses\Domain\AnswerEvaluation;
use App\Responses\Domain\AnswerValues;
use PHPUnit\Framework\TestCase;

/** The answers of a session as webhooks and the external API send them (PRD §7.14), and the §7.9 pass rule. */
final class AnswerValuesTest extends TestCase
{
    public function testEachControlTypeHasItsValueFormat(): void
    {
        $answers = AnswerValues::of([
            self::q('Voice', 'audio', ['first take', 'second take']),
            self::q('Text', 'text', ['a', 'b']),
            self::q('Files', 'file', ['ACME/s/q/abc.pdf']),
            self::q('Range', 'range', '7', validations: [['type' => 'min', 'value' => 1], ['type' => 'max', 'value' => 10]]),
            self::q('Radio', 'radio', '2', options: [['label' => 'Low', 'value' => '1'], ['label' => 'High', 'value' => '2']]),
            self::q('Checks', 'checkbox', ['1', '2'], options: [['label' => 'Red', 'value' => '1'], ['label' => 'Blue', 'value' => '2']]),
            self::q('Words', 'select', 'green', options: [['label' => 'Green', 'value' => 'green']]),
            self::q('Slide', 'message', null),
            self::q('Email', 'email', 'ana@acme.test'),
        ]);

        self::assertSame([
            ['title' => 'Voice', 'value' => ['first take', 'second take']],
            ['title' => 'Text', 'value' => 'a, b'],
            ['title' => 'Files', 'value' => ['ACME/s/q/abc.pdf']],
            ['title' => 'Range', 'value' => 7, 'min' => 1, 'max' => 10],
            ['title' => 'Radio', 'value' => 'High'],
            ['title' => 'Checks', 'value' => ['Red', 'Blue']],
            ['title' => 'Words', 'value' => 'green'],
            ['title' => 'Email', 'value' => 'ana@acme.test'],
        ], $answers, '§7.14: audio a list, text a string, files keys, range a number with min/max, labels for numeric selections; message slides omitted');
    }

    public function testAnAnswerPassesWhenRelatedAndItsCriteriaAverageAtLeast30(): void
    {
        self::assertTrue(AnswerEvaluation::passes(true, [30, 30]), '§7.9: average ≥ 30 and related passes');
        self::assertFalse(AnswerEvaluation::passes(true, [50, 9]), 'an average of 29.5 does not pass');
        self::assertFalse(AnswerEvaluation::passes(false, [50, 50]), 'an unrelated answer never passes');
        self::assertTrue(AnswerEvaluation::passes(true, []), 'without criteria, being related is enough');
    }

    public function testOnlyTextAndAudioAnswersWithFollowUpsLeftAreEvaluated(): void
    {
        self::assertTrue(AnswerEvaluation::applies(self::q('T', 'text', 'my answer') + ['max_followups' => 1]));
        self::assertFalse(AnswerEvaluation::applies(self::q('T', 'text', 'my answer') + ['max_followups' => 0]), 'no retries left');
        self::assertFalse(AnswerEvaluation::applies(self::q('T', 'text', '  ') + ['max_followups' => 2]), 'an empty answer');
        self::assertFalse(AnswerEvaluation::applies(self::q('R', 'radio', '1') + ['max_followups' => 2]), 'only text or audio');
        self::assertTrue(AnswerEvaluation::applies(self::q('A', 'audio', ['spoken']) + ['max_followups' => 1]));
    }

    /**
     * @param list<array<string, mixed>> $options
     * @param list<array<string, mixed>> $validations
     *
     * @return array<string, mixed>
     */
    private static function q(string $title, string $type, mixed $value, array $options = [], array $validations = []): array
    {
        return [
            'id' => strtolower($title),
            'title' => $title,
            'options' => [['name' => 'c', 'type' => $type, 'options' => $options, 'validations' => $validations, 'value' => $value]],
        ];
    }
}
