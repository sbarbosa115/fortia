<?php

namespace App\Responses\Domain;

use App\Shared\Domain\Document\Questions;

/**
 * How the respondent's answers get into a stored session.
 *
 * The respondent sends the whole session, but only the values are theirs: per control `value`, `skipped` and
 * `timestamp`. The rest — titles, controls, follow-up counters (§7.9), reviews and locks (§7.11) — is kept from the
 * stored session. A locked control never changes.
 *
 * In a follow-up's shared session (§7.11) several members write to the same session: an empty incoming value does
 * not overwrite an existing one, and an answer wins over a skip.
 */
final class SessionAnswers
{
    /**
     * @param list<array<string, mixed>> $stored   the session's questions
     * @param array<int|string, mixed>   $incoming the questions the respondent sent
     *
     * @return list<array<string, mixed>>
     */
    public static function apply(array $stored, array $incoming, bool $followUp): array
    {
        $byId = [];
        foreach ($incoming as $question) {
            if (\is_array($question) && isset($question['id']) && \is_scalar($question['id'])) {
                $byId[(string) $question['id']] = $question;
            }
        }

        foreach ($stored as $i => $question) {
            $sent = $byId[(string) ($question['id'] ?? '')] ?? null;
            if (null === $sent) {
                continue;
            }
            $sentControls = array_values(array_filter((array) ($sent['options'] ?? []), 'is_array'));
            foreach ((array) ($question['options'] ?? []) as $j => $control) {
                if (!\is_array($control)) {
                    continue;
                }
                $match = self::matchingControl($sentControls, (string) ($control['name'] ?? ''), (int) $j);
                if (null === $match || true === ($control['locked'] ?? false)) {
                    continue;
                }
                $stored[$i]['options'][$j] = $followUp ? self::merge($control, $match) : self::replace($control, $match);
            }
        }

        return $stored;
    }

    /**
     * The next attempt of a follow-up (§7.11 "Retry", step 2): approved answers (and those locked before) are copied
     * and locked; every other answer is cleared. Reviews stay.
     *
     * @param list<array<string, mixed>> $previous the questions of the attempt being corrected
     *
     * @return list<array<string, mixed>>
     */
    public static function carryOverForRetry(array $previous): array
    {
        foreach ($previous as $i => $question) {
            $approved = 'approved' === ($question['review']['status'] ?? null) || self::isLocked($question);
            foreach ((array) ($question['options'] ?? []) as $j => $control) {
                if (!\is_array($control)) {
                    continue;
                }
                if ($approved) {
                    $previous[$i]['options'][$j]['locked'] = true;
                } else {
                    $previous[$i]['options'][$j]['value'] = null;
                    $previous[$i]['options'][$j]['skipped'] = false;
                    $previous[$i]['options'][$j]['timestamp'] = null;
                    $previous[$i]['options'][$j]['locked'] = null;
                }
            }
            if (!$approved) {
                $previous[$i]['improvement_message'] = null;
                $previous[$i]['flagged_answer'] = null;
            }
        }

        return $previous;
    }

    /**
     * Writes a reviewer's decision on a question: {status, comment, reviewed_at, attempt}.
     *
     * @param list<array<string, mixed>> $questions
     * @param array<string, mixed>       $review
     *
     * @return list<array<string, mixed>>
     */
    public static function review(array $questions, string $questionId, array $review): array
    {
        foreach ($questions as $i => $question) {
            if ((string) ($question['id'] ?? '') === $questionId) {
                $questions[$i]['review'] = $review;
            }
        }

        return $questions;
    }

    /**
     * The outcome of an answer's AI evaluation (§7.9): not passing costs one follow-up (never below 0) and keeps the
     * improvement message and the answer that was flagged; passing clears them.
     *
     * @param list<array<string, mixed>> $questions
     *
     * @return list<array<string, mixed>>
     */
    public static function evaluated(array $questions, string $questionId, bool $passed, ?string $improvementMessage, mixed $flaggedAnswer): array
    {
        foreach ($questions as $i => $question) {
            if ((string) ($question['id'] ?? '') !== $questionId) {
                continue;
            }
            if ($passed) {
                $questions[$i]['improvement_message'] = null;
                $questions[$i]['flagged_answer'] = null;
            } else {
                $questions[$i]['max_followups'] = max(0, (int) ($question['max_followups'] ?? 0) - 1);
                $questions[$i]['improvement_message'] = $improvementMessage;
                $questions[$i]['flagged_answer'] = $flaggedAnswer;
            }
        }

        return $questions;
    }

    /** @param array<string, mixed> $question */
    public static function isLocked(array $question): bool
    {
        foreach ((array) ($question['options'] ?? []) as $control) {
            if (\is_array($control) && true === ($control['locked'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $controls
     *
     * @return array<string, mixed>|null
     */
    private static function matchingControl(array $controls, string $name, int $position): ?array
    {
        foreach ($controls as $control) {
            if ('' !== $name && (string) ($control['name'] ?? '') === $name) {
                return $control;
            }
        }

        return $controls[$position] ?? null;
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $sent
     *
     * @return array<string, mixed>
     */
    private static function replace(array $stored, array $sent): array
    {
        $stored['value'] = self::cleanValue($sent['value'] ?? null);
        $stored['skipped'] = true === ($sent['skipped'] ?? false);
        $stored['timestamp'] = self::timestamp($sent['timestamp'] ?? null) ?? ($stored['timestamp'] ?? null);

        return $stored;
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $sent
     *
     * @return array<string, mixed>
     */
    private static function merge(array $stored, array $sent): array
    {
        $value = self::cleanValue($sent['value'] ?? null);
        if (Questions::hasValue($value)) {
            $stored['value'] = $value;
            $stored['skipped'] = false;
            $stored['timestamp'] = self::timestamp($sent['timestamp'] ?? null) ?? ($stored['timestamp'] ?? null);

            return $stored;
        }
        if (Questions::hasValue($stored['value'] ?? null)) {
            // An answer wins over a skip, and an empty value never erases it.
            $stored['skipped'] = false;

            return $stored;
        }
        if (true === ($sent['skipped'] ?? false)) {
            $stored['skipped'] = true;
            $stored['timestamp'] = self::timestamp($sent['timestamp'] ?? null) ?? ($stored['timestamp'] ?? null);
        }

        return $stored;
    }

    /** A value is a string, a number or a list of them; anything else is dropped. */
    private static function cleanValue(mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_values(array_filter($value, static fn ($v): bool => \is_string($v) || \is_int($v) || \is_float($v)));
        }

        return \is_string($value) || \is_int($value) || \is_float($value) ? $value : null;
    }

    private static function timestamp(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? mb_substr($value, 0, 40) : null;
    }
}
