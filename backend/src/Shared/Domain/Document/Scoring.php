<?php

declare(strict_types=1);

namespace App\Shared\Domain\Document;

/**
 * Score arithmetic shared by authoring (the diagnostic must be scorable, PRD §7.5) and by the respondent's results
 * (§7.7).
 */
final class Scoring
{
    /**
     * The maximum score of a question: the sum over its controls. A checkbox or ranking control contributes the sum
     * of its option values; any other control its highest value (PRD §7.5). A range contributes its "max"
     * validation.
     *
     * @param array<string, mixed> $question
     */
    public static function maxScore(array $question): float
    {
        $max = 0.0;
        foreach ((array) ($question['options'] ?? []) as $control) {
            if (\is_array($control)) {
                $max += self::controlMax($control);
            }
        }

        return $max;
    }

    /** @param array<string, mixed> $control */
    public static function controlMax(array $control): float
    {
        $type = ControlType::tryFrom((string) ($control['type'] ?? ''));
        if (ControlType::Range === $type) {
            $bounds = self::rangeBounds($control);

            return max(0.0, $bounds[1]);
        }
        $values = [];
        foreach ((array) ($control['options'] ?? []) as $option) {
            $number = \is_array($option) ? self::number($option['value'] ?? null) : null;
            if (null !== $number) {
                $values[] = $number;
            }
        }
        if ([] === $values) {
            return 0.0;
        }
        if (ControlType::Checkbox === $type || ControlType::Ranking === $type) {
            return array_sum($values);
        }

        return max($values);
    }

    /**
     * The min and max of a range control, from its validations; 0 and 10 by default (PRD §9.4).
     *
     * @param array<string, mixed> $control
     *
     * @return array{0: float, 1: float}
     */
    public static function rangeBounds(array $control): array
    {
        $min = 0.0;
        $max = 10.0;
        foreach ((array) ($control['validations'] ?? []) as $validation) {
            if (!\is_array($validation)) {
                continue;
            }
            $number = self::number($validation['value'] ?? null);
            if (null === $number) {
                continue;
            }
            if ('min' === ($validation['type'] ?? null)) {
                $min = $number;
            } elseif ('max' === ($validation['type'] ?? null)) {
                $max = $number;
            }
        }

        return [$min, $max];
    }

    /** A numeric option value, or null when it is text. */
    public static function number(mixed $value): ?float
    {
        if (\is_int($value) || \is_float($value)) {
            return (float) $value;
        }
        if (\is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return null;
    }

    /** Rounds half up (PRD §7.7: "Total = sum of categories, rounded half-up"). */
    public static function roundHalfUp(float $value): int
    {
        return (int) floor($value + 0.5);
    }
}
