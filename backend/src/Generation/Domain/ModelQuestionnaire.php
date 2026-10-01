<?php

namespace App\Generation\Domain;

use App\Shared\Domain\Document\GeneratedQuestions;
use App\Shared\Domain\Document\Scoring;

/**
 * What the language model returns when it writes a questionnaire (a chain stage, PRD §7.8, or a LinkedIn
 * diagnostic, §7.18), and how it becomes stored questions: a compact shape the model fills through a JSON schema
 * (one control per question), mapped onto the PRD §6.5 Question and cleaned by §7.6.
 *
 * The model never gives scores or bands: a scored questionnaire asks it for categories and numeric option values,
 * and the tiers' bands are computed here (TierBands).
 */
final class ModelQuestionnaire
{
    public const CONTROL_TYPES = ['radio', 'checkbox', 'select', 'text'];
    public const MAX_QUESTIONS = 30;

    /**
     * The JSON schema of the answer.
     *
     * @return array<string, mixed>
     */
    public static function schema(bool $withTiers): array
    {
        $texts = ['type' => 'array', 'items' => ['type' => 'string']];
        $properties = [
            'title' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'questions' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => self::CONTROL_TYPES],
                        'choices' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => ['label' => ['type' => 'string'], 'value' => ['type' => ['number', 'null']]],
                                'required' => ['label', 'value'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['title', 'description', 'category', 'type', 'choices'],
                    'additionalProperties' => false,
                ],
            ],
        ];
        $required = ['title', 'description', 'questions'];
        if ($withTiers) {
            $properties['tiers'] = [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['name' => ['type' => 'string'], 'description' => ['type' => 'string'], 'recommendations' => $texts, 'action_plan' => $texts],
                    'required' => ['name', 'description', 'recommendations', 'action_plan'],
                    'additionalProperties' => false,
                ],
            ];
            $required[] = 'tiers';
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    /**
     * The model's questions as stored questions, cleaned (§7.6). In a scored questionnaire every choice has a
     * number (the position when the model gave none).
     *
     * @param array<string, mixed>|null $json
     *
     * @return list<array<string, mixed>>
     *
     * @throws \App\Shared\Domain\Error\UpstreamFailed when no question is left
     */
    public static function questions(?array $json, bool $scored): array
    {
        $questions = [];
        foreach (\array_slice(\is_array($json['questions'] ?? null) ? array_values($json['questions']) : [], 0, self::MAX_QUESTIONS) as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $title = self::text($item['title'] ?? null);
            $type = \in_array($item['type'] ?? null, self::CONTROL_TYPES, true) ? (string) $item['type'] : null;
            if ('' === $title || null === $type) {
                continue;
            }
            $control = ['type' => $type];
            if ('text' !== $type) {
                $choices = [];
                foreach (\is_array($item['choices'] ?? null) ? array_values($item['choices']) : [] as $i => $choice) {
                    $label = \is_array($choice) ? self::text($choice['label'] ?? null) : '';
                    if ('' === $label) {
                        continue;
                    }
                    $value = \is_int($choice['value'] ?? null) || \is_float($choice['value'] ?? null) ? $choice['value'] : null;
                    $choices[] = ['label' => $label, 'value' => $scored ? ($value ?? $i) : $value];
                }
                if (\count($choices) < 2) {
                    continue;
                }
                $control['options'] = $choices;
            }
            $category = self::text($item['category'] ?? null);
            $description = self::text($item['description'] ?? null);
            $questions[] = [
                'title' => $title,
                'description' => '' === $description ? null : $description,
                'category' => '' === $category || 'text' === $type ? null : $category,
                'options' => [$control],
            ];
        }

        return GeneratedQuestions::clean($questions);
    }

    /**
     * The maximum score of scored questions (those with a category, §7.7), rounded half-up as a respondent's total.
     *
     * @param list<array<string, mixed>> $questions
     */
    public static function maxScore(array $questions): int
    {
        $max = 0.0;
        foreach ($questions as $question) {
            $category = $question['category'] ?? null;
            if (\is_string($category) && '' !== trim($category)) {
                $max += Scoring::maxScore($question);
            }
        }

        return Scoring::roundHalfUp($max);
    }

    /** @param array<string, mixed>|null $json */
    public static function title(?array $json, string $fallback): string
    {
        $title = mb_substr(self::text($json['title'] ?? null), 0, 200);

        return '' === $title ? $fallback : $title;
    }

    /** @param array<string, mixed>|null $json */
    public static function description(?array $json): ?string
    {
        $description = self::text($json['description'] ?? null);

        return '' === $description ? null : $description;
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }
}
