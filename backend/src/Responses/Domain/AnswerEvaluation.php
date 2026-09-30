<?php

namespace App\Responses\Domain;

use App\Shared\Domain\Document\ControlType;
use App\Shared\Domain\Document\Questions;

/**
 * The AI evaluation of an answer (PRD §7.9): it applies to text or audio questions with follow-ups left and a
 * non-empty answer. The language model grades each acceptance criterion from 0 to 50; the answer passes when it is
 * related to the question and the average is at least 30.
 */
final class AnswerEvaluation
{
    public const PASS_AVERAGE = 30;
    public const MAX_GRADE = 50;

    /** @param array<string, mixed> $question the question with the answer to evaluate */
    public static function applies(array $question): bool
    {
        $type = Questions::controlType($question);

        return \in_array($type, [ControlType::Text, ControlType::Audio], true)
            && (int) ($question['max_followups'] ?? 0) > 0
            && [] !== Questions::values($question);
    }

    /** @param list<int|float> $grades one per criterion, 0–50 */
    public static function passes(bool $related, array $grades): bool
    {
        if (!$related) {
            return false;
        }
        if ([] === $grades) {
            return true;
        }
        $grades = array_map(static fn ($g): float => max(0.0, min((float) self::MAX_GRADE, (float) $g)), $grades);

        return array_sum($grades) / \count($grades) >= self::PASS_AVERAGE;
    }

    /**
     * The answer as text for the language model: the values joined (audio segments, text lists).
     *
     * @param array<string, mixed> $question
     */
    public static function answerText(array $question): string
    {
        return implode("\n", Questions::values($question));
    }
}
