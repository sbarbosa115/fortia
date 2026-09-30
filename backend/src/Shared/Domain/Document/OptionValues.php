<?php

declare(strict_types=1);

namespace App\Shared\Domain\Document;

/**
 * Duplicate option values within a control are fixed automatically (PRD §7.5): a repeated number is bumped above
 * the highest one in use; a repeated text gets a "-2", "-3"… suffix.
 */
final class OptionValues
{
    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return list<array<string, mixed>>
     */
    public static function dedupeQuestions(array $questions): array
    {
        foreach ($questions as $i => $question) {
            foreach ((array) ($question['options'] ?? []) as $j => $control) {
                if (\is_array($control)) {
                    $questions[$i]['options'][$j]['options'] = self::dedupe((array) ($control['options'] ?? []));
                }
            }
        }

        return $questions;
    }

    /**
     * @param array<int|string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    public static function dedupe(array $options): array
    {
        $options = array_values(array_filter($options, 'is_array'));
        $highest = null;
        foreach ($options as $option) {
            $number = Scoring::number($option['value'] ?? null);
            if (null !== $number) {
                $highest = null === $highest ? $number : max($highest, $number);
            }
        }

        $seen = [];
        foreach ($options as $i => $option) {
            $value = $option['value'] ?? null;
            if (null === $value || '' === $value) {
                continue;
            }
            $key = (string) $value;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                continue;
            }
            $number = Scoring::number($value);
            if (null !== $number) {
                $highest = ($highest ?? $number) + 1;
                $new = floor($highest) === $highest ? (int) $highest : $highest;
                $options[$i]['value'] = \is_string($value) ? (string) $new : $new;
            } else {
                $suffix = 2;
                while (isset($seen[$key.'-'.$suffix])) {
                    ++$suffix;
                }
                $options[$i]['value'] = $key.'-'.$suffix;
            }
            $seen[(string) $options[$i]['value']] = true;
        }

        return $options;
    }
}
