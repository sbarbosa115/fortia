<?php

declare(strict_types=1);

namespace App\Shared\Domain\Document;

/** PRD §6.5 Questionnaire.type. */
enum QuestionnaireType: string
{
    case Default = 'default';
    case Ecommerce = 'ecommerce';
    case QuizFunnel = 'quiz_funnel';
    case Samurai8 = 'samurai8';
    case AiTeamProfile = 'ai_team_profile';
    case Diagnostic = 'diagnostic';
    case Prompt = 'prompt';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}
