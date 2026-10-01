<?php

namespace App\Responses\Domain;

use App\Shared\Domain\Document\ControlType;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Document\Scoring;
use App\Shared\Domain\Document\TableAnswer;

/**
 * The answers of a session as the outgoing webhook and the external API send them (PRD §7.14):
 * [{title, value, min?, max?}], message slides omitted.
 *
 * | Control   | value                                                                        |
 * |-----------|------------------------------------------------------------------------------|
 * | audio     | the list of transcriptions                                                   |
 * | text…     | a string (a list is joined with ", ")                                        |
 * | file      | the list of storage keys                                                     |
 * | range     | a number, with min and max                                                   |
 * | table     | the rows, each {column label: text}, a fixed row's label under "row"         |
 * | selection | the option labels when the values are numeric; a list for checkbox, ranking  |
 *
 * An unanswered question has a null value.
 */
final class AnswerValues
{
    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return list<array{title: string, value: mixed, min?: int|float, max?: int|float}>
     */
    public static function of(array $questions): array
    {
        $answers = [];
        foreach ($questions as $question) {
            $control = Questions::control($question);
            $type = null === $control ? null : ControlType::tryFrom((string) $control['type']);
            if (null === $control || null === $type || ControlType::Message === $type) {
                continue;
            }
            $answers[] = self::answer((string) ($question['title'] ?? ''), $type, $control, Questions::values($question));
        }

        return $answers;
    }

    /**
     * @param array<string, mixed> $control
     * @param list<string>         $values
     *
     * @return array{title: string, value: mixed, min?: int|float, max?: int|float}
     */
    private static function answer(string $title, ControlType $type, array $control, array $values): array
    {
        if (ControlType::Table === $type) {
            $rows = TableAnswer::labelled($control['value'] ?? null, $control);

            return ['title' => $title, 'value' => [] === $rows ? null : $rows];
        }
        if (ControlType::Range === $type) {
            [$min, $max] = Scoring::rangeBounds($control);
            $number = Scoring::number($values[0] ?? null);

            return ['title' => $title, 'value' => null === $number ? null : self::number($number), 'min' => self::number($min), 'max' => self::number($max)];
        }
        if ([] === $values) {
            return ['title' => $title, 'value' => null];
        }
        if (ControlType::Audio === $type || ControlType::File === $type) {
            return ['title' => $title, 'value' => $values];
        }
        if ($type->isSelection()) {
            $labels = array_map(static fn (string $v): string => self::labelOf($control, $v), $values);

            return ['title' => $title, 'value' => $type->isMultiValued() ? $labels : $labels[0]];
        }

        return ['title' => $title, 'value' => implode(', ', $values)];
    }

    /**
     * A numeric value is reported by its label; a text value is already readable.
     *
     * @param array<string, mixed> $control
     */
    private static function labelOf(array $control, string $value): string
    {
        if (null === Scoring::number($value)) {
            return $value;
        }
        foreach ((array) ($control['options'] ?? []) as $option) {
            if (\is_array($option) && isset($option['value']) && (string) $option['value'] === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value;
    }

    private static function number(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : $value;
    }
}
