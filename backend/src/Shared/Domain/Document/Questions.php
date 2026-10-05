<?php

namespace App\Shared\Domain\Document;

/**
 * The questions of a questionnaire (and of a session, which is a copy with values), as the JSON documents of
 * PRD §6.5. They are stored as documents; these functions give them one normalized shape and answer the questions
 * several contexts ask about them.
 *
 * A question array has the keys of PRD §6.5 Question; a control array those of InputControl.
 */
final class Questions
{
    /** Keys a respondent's run fills in; never part of a stored questionnaire. */
    public const RUNTIME_QUESTION_KEYS = ['improvement_message', 'flagged_answer', 'review'];
    public const RUNTIME_CONTROL_KEYS = ['value', 'timestamp', 'skipped', 'locked'];

    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return list<array<string, mixed>>
     */
    public static function normalizeAll(array $questions, bool $keepRuntime = false): array
    {
        $out = [];
        foreach ($questions as $i => $question) {
            $out[] = self::normalize($question, $i, $keepRuntime);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $q
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $q, int $position = 0, bool $keepRuntime = false): array
    {
        $question = [
            'id' => (string) ($q['id'] ?? ''),
            'order' => isset($q['order']) ? (int) $q['order'] : $position,
            'title' => (string) ($q['title'] ?? ''),
            'description' => self::nullableString($q['description'] ?? null),
            'disclaimer' => self::nullableString($q['disclaimer'] ?? null),
            'theme_name' => self::nullableString($q['theme_name'] ?? null),
            'statements' => $q['statements'] ?? null,
            'visibility' => self::stringList($q['visibility'] ?? []),
            'options' => array_map(
                static fn (array $c): array => self::normalizeControl($c, $keepRuntime),
                array_values(array_filter((array) ($q['options'] ?? []), 'is_array')),
            ),
            'acceptance_criteria' => \array_slice(self::stringList($q['acceptance_criteria'] ?? []), 0, 10),
            'max_followups' => isset($q['max_followups']) ? max(0, min(5, (int) $q['max_followups'])) : null,
            'attachment_required' => isset($q['attachment_required']) ? (bool) $q['attachment_required'] : null,
            'category' => self::nullableString($q['category'] ?? null),
            'required' => (bool) ($q['required'] ?? true),
        ];
        if ($keepRuntime) {
            $question['improvement_message'] = self::nullableString($q['improvement_message'] ?? null);
            $question['flagged_answer'] = $q['flagged_answer'] ?? null;
            $question['review'] = \is_array($q['review'] ?? null) ? $q['review'] : null;
        }

        return $question;
    }

    /**
     * @param array<string, mixed> $c
     *
     * @return array<string, mixed>
     */
    public static function normalizeControl(array $c, bool $keepRuntime = false): array
    {
        $options = [];
        foreach ((array) ($c['options'] ?? []) as $option) {
            if (!\is_array($option)) {
                continue;
            }
            $options[] = [
                'label' => (string) ($option['label'] ?? ''),
                'value' => isset($option['value']) && '' !== $option['value'] ? $option['value'] : null,
                'visibility' => self::stringList($option['visibility'] ?? []),
            ];
        }
        $validations = [];
        foreach ((array) ($c['validations'] ?? []) as $validation) {
            if (!\is_array($validation) || !isset($validation['type'])) {
                continue;
            }
            $validations[] = array_filter([
                'type' => (string) $validation['type'],
                'value' => $validation['value'] ?? null,
                'message' => $validation['message'] ?? null,
                'pattern' => $validation['pattern'] ?? null,
            ], static fn ($v): bool => null !== $v);
        }
        $control = [
            'name' => (string) ($c['name'] ?? ''),
            'type' => (string) ($c['type'] ?? ControlType::Message->value),
            'options' => $options,
            'validations' => $validations,
            'default_value' => $c['default_value'] ?? null,
        ];
        // A table's fixed rows and a file question's template exist only on those controls.
        $type = ControlType::tryFrom($control['type']);
        if (ControlType::Table === $type && [] !== ($rows = TableAnswer::rowLabels($c['rows'] ?? null))) {
            $control['rows'] = $rows;
        } elseif (ControlType::Table === $type && null !== ($maxRows = TableAnswer::maxRowsOf($c['max_rows'] ?? null))) {
            $control['max_rows'] = $maxRows;
        }
        if (ControlType::File === $type && null !== ($template = FileTemplate::normalize($c['template'] ?? null))) {
            $control['template'] = $template;
        }
        if ($keepRuntime) {
            $control['value'] = $c['value'] ?? null;
            $control['timestamp'] = self::nullableString($c['timestamp'] ?? null);
            $control['skipped'] = (bool) ($c['skipped'] ?? false);
            $control['locked'] = isset($c['locked']) ? (bool) $c['locked'] : null;
        }

        return $control;
    }

    /**
     * The control a question is answered with: the first one that can be rendered (PRD §6.5, §9.4).
     *
     * @param array<string, mixed> $question
     *
     * @return array<string, mixed>|null
     */
    public static function control(array $question): ?array
    {
        foreach ((array) ($question['options'] ?? []) as $control) {
            if (\is_array($control) && null !== ControlType::tryFrom((string) ($control['type'] ?? ''))) {
                return $control;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $question */
    public static function controlType(array $question): ?ControlType
    {
        $control = self::control($question);

        return null === $control ? null : ControlType::from((string) $control['type']);
    }

    /**
     * A question that asks for an answer: anything but a message slide (or a question without a control).
     *
     * @param array<string, mixed> $question
     */
    public static function isAnswerable(array $question): bool
    {
        $type = self::controlType($question);

        return null !== $type && ControlType::Message !== $type;
    }

    /**
     * Whether the respondent answered or skipped it (PRD §7.5 "any response (value or skip)").
     *
     * @param array<string, mixed> $question
     */
    public static function isResolved(array $question): bool
    {
        foreach ((array) ($question['options'] ?? []) as $control) {
            if (!\is_array($control)) {
                continue;
            }
            if (true === ($control['skipped'] ?? false) || self::hasValue($control['value'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $question */
    public static function isAnswered(array $question): bool
    {
        foreach ((array) ($question['options'] ?? []) as $control) {
            if (\is_array($control) && self::hasValue($control['value'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    public static function hasValue(mixed $value): bool
    {
        if (\is_array($value)) {
            foreach ($value as $item) {
                if (self::hasValue($item)) {
                    return true;
                }
            }

            return false;
        }

        return null !== $value && '' !== trim((string) (\is_scalar($value) ? $value : ''));
    }

    /**
     * The selected values of a question's control, as a list of strings.
     *
     * @param array<string, mixed> $question
     *
     * @return list<string>
     */
    public static function values(array $question): array
    {
        $control = self::control($question);
        if (null === $control) {
            return [];
        }
        $value = $control['value'] ?? null;
        if (ControlType::Table->value === ($control['type'] ?? null)) {
            // A table's rows are not values to select, score or count.
            return [];
        }
        $values = \is_array($value) ? $value : [$value];

        return array_values(array_map(
            static fn ($v): string => (string) $v,
            array_filter($values, static fn ($v): bool => \is_scalar($v) && '' !== trim((string) $v)),
        ));
    }

    /**
     * Removes every runtime field (§7.6 step 3): what a stored questionnaire must not carry.
     *
     * @param list<array<string, mixed>> $questions
     *
     * @return list<array<string, mixed>>
     */
    public static function withoutRuntime(array $questions): array
    {
        return self::normalizeAll($questions, false);
    }

    private static function nullableString(mixed $value): ?string
    {
        if (null === $value || !\is_scalar($value)) {
            return null;
        }
        $value = (string) $value;

        return '' === $value ? null : $value;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_map('strval', array_filter($value, 'is_scalar')));
    }
}
