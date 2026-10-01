<?php

namespace App\Commerce\Domain;

use App\Shared\Domain\Document\GeneratedQuestions;
use App\Shared\Domain\Ids;

/**
 * The quiz funnel a store gets (PRD §7.17, §10.5 Quiz Funnel): what the language model is asked for and how its
 * answer becomes the flow the questionnaires context stores.
 *
 * - Variants: `experience` ("Design Experience", the product questionnaire rules) or `profiling`.
 * - The questionnaire is of type `ecommerce`, its on_completed is `quiz_funnel`, and the flow has two states
 *   (`questionnaire` → `quiz_funnel`) and a random lowercase slug.
 * - The model gives one control per question (radio, checkbox or select with labelled choices); the answer is
 *   cleaned by §7.6 (GeneratedQuestions). Option values are left to the platform (slug of the label).
 */
final class QuizFunnel
{
    public const EXPERIENCE = 'experience';
    public const PROFILING = 'profiling';
    public const VARIANTS = [self::EXPERIENCE, self::PROFILING];

    public const PURPOSE_EXPERIENCE = 'quiz-funnel--rules-to-create-product-questionnaires';
    public const PURPOSE_PROFILING = 'quiz-funnel--rules-to-create-profiling-questionnaires';
    public const CONTROL_TYPES = ['radio', 'checkbox', 'select'];
    public const MAX_QUESTIONS = 12;
    public const MAX_CATALOG_IN_PROMPT = 60;

    public static function purpose(string $variant): string
    {
        return self::PROFILING === $variant ? self::PURPOSE_PROFILING : self::PURPOSE_EXPERIENCE;
    }

    /** A random lowercase slug (§7.17 step 4): "qf-" and 10 lowercase letters or digits. */
    public static function randomSlug(): string
    {
        return 'qf-'.strtolower(Ids::alphanumeric(10));
    }

    /** "es" or "en" from an account language such as "es-CO" (Spanish is the default, §14.4). */
    public static function language(?string $accountLanguage): string
    {
        return str_starts_with(strtolower((string) $accountLanguage), 'en') ? 'en' : 'es';
    }

    /** @return array<string, mixed> the JSON schema of the model's answer */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => self::CONTROL_TYPES],
                            'choices' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['title', 'description', 'type', 'choices'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['title', 'description', 'questions'],
            'additionalProperties' => false,
        ];
    }

    /**
     * The model's answer as stored questions, cleaned (§7.6).
     *
     * @param array<string, mixed>|null $json
     *
     * @return list<array<string, mixed>>
     *
     * @throws \App\Shared\Domain\Error\UpstreamFailed GENERATION_FAILED when no question is left
     */
    public static function questions(?array $json): array
    {
        $questions = [];
        foreach (\array_slice(\is_array($json['questions'] ?? null) ? array_values($json['questions']) : [], 0, self::MAX_QUESTIONS) as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $title = self::text($item['title'] ?? null, 300);
            $type = \in_array($item['type'] ?? null, self::CONTROL_TYPES, true) ? (string) $item['type'] : 'radio';
            $choices = [];
            foreach (\is_array($item['choices'] ?? null) ? $item['choices'] : [] as $choice) {
                $label = self::text(\is_array($choice) ? ($choice['label'] ?? null) : $choice, 200);
                if ('' !== $label && !\in_array($label, $choices, true)) {
                    $choices[] = $label;
                }
            }
            if ('' === $title || \count($choices) < 2) {
                continue;
            }
            $description = self::text($item['description'] ?? null, 500);
            $questions[] = [
                'title' => $title,
                'description' => '' === $description ? null : $description,
                'required' => true,
                'options' => [[
                    'type' => $type,
                    'options' => array_map(static fn (string $label): array => ['label' => $label, 'value' => null], \array_slice($choices, 0, 10)),
                ]],
            ];
        }

        return GeneratedQuestions::clean($questions);
    }

    /** @param array<string, mixed>|null $json */
    public static function title(?array $json, string $fallback): string
    {
        $title = self::text($json['title'] ?? null, 200);

        return '' === $title ? $fallback : $title;
    }

    /** @param array<string, mixed>|null $json */
    public static function description(?array $json): ?string
    {
        $description = self::text($json['description'] ?? null, 1000);

        return '' === $description ? null : $description;
    }

    /**
     * The flow SaveFlow stores (§7.17 step 4): `questionnaire` → `quiz_funnel`.
     *
     * @param list<array<string, mixed>> $questions
     *
     * @return list<array<string, mixed>>
     */
    public static function states(string $title, ?string $description, array $questions): array
    {
        return [
            [
                'state_id' => 'questionnaire',
                'type' => 'questionnaire',
                'next' => 'quiz_funnel',
                'parameters' => ['questionnaire' => [
                    'title' => $title,
                    'description' => $description,
                    'type' => 'ecommerce',
                    'landing_page' => true,
                    'capture_user_data' => false,
                    'questions' => $questions,
                    'on_completed' => ['type' => 'quiz_funnel', 'products' => []],
                ]],
            ],
            ['state_id' => 'quiz_funnel', 'type' => 'quiz_funnel', 'parameters' => []],
        ];
    }

    private static function text(mixed $value, int $max): string
    {
        return \is_scalar($value) ? mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $value)), 0, $max) : '';
    }
}
