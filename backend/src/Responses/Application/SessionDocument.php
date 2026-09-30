<?php

namespace App\Responses\Application;

use App\Questionnaires\Application\Query\QuestionnaireView;
use App\Shared\Domain\Document\Questions;

/**
 * The copy of the questionnaire a session starts from (PRD §6.9): its texts, flags and questions with the runtime
 * fields empty. on_completed keeps only its type: the scoring configuration is read from the questionnaire when the
 * session is submitted, never from what a respondent sent or saw (§8.4).
 */
final class SessionDocument
{
    /**
     * @param list<array<string, mixed>>|null $questions the questions to start with (a retry carries answers over)
     *
     * @return array<string, mixed>
     */
    public static function of(QuestionnaireView $questionnaire, ?array $questions = null): array
    {
        $q = $questionnaire->data;
        $onCompleted = $questionnaire->onCompleted();

        return [
            'title' => $q['title'] ?? '',
            'description' => $q['description'] ?? null,
            'disclaimer' => $q['disclaimer'] ?? null,
            'capture_user_data' => (bool) ($q['capture_user_data'] ?? false),
            'landing_page' => (bool) ($q['landing_page'] ?? false),
            'type' => $q['type'] ?? 'default',
            'is_active' => (bool) ($q['is_active'] ?? true),
            'on_completed' => null === $onCompleted ? null : ['type' => (string) ($onCompleted['type'] ?? 'default')],
            'parent' => $q['parent'] ?? 'ROOT',
            'slug' => $q['slug'] ?? null,
            'questions' => $questions ?? Questions::normalizeAll($questionnaire->questions(), true),
        ];
    }
}
