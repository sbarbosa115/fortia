<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Domain\Model\Feature;

/** The canonical feature slugs (PRD §6.3), for other contexts to gate on: $gate->capacity($caller, Features::USERS). */
final class Features
{
    public const REGULAR = Feature::REGULAR;
    public const DIAGNOSTIC = Feature::DIAGNOSTIC;
    public const QUIZ_FUNNEL = Feature::QUIZ_FUNNEL;
    public const CHAIN = Feature::CHAIN;
    public const CHAT = Feature::CHAT;
    public const ORGANIZATIONS = Feature::ORGANIZATIONS;
    public const ASSIGNATIONS = Feature::ASSIGNATIONS;
    public const STYLES = Feature::STYLES;
    public const ANALYTICS = Feature::ANALYTICS;
    public const DASHBOARDS = Feature::DASHBOARDS;
    public const USERS = Feature::USERS;
    public const API = Feature::API;
    public const WEBHOOK = Feature::WEBHOOK;
    public const PROFILE = Feature::PROFILE;
    public const RESPONSES = Feature::RESPONSES;

    /** The feature a new questionnaire counts against, by its flow type (PRD §7.2). */
    public static function forQuestionnaireType(string $flowType): string
    {
        return match ($flowType) {
            'diagnostic' => self::DIAGNOSTIC,
            'quiz_funnel', 'ecommerce' => self::QUIZ_FUNNEL,
            'prompt', 'chain' => self::CHAIN,
            default => self::REGULAR,
        };
    }
}
