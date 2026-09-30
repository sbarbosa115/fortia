<?php

namespace App\Responses\Application;

use App\Responses\Application\Port\ResultTypeSettings;

/**
 * The client-specific result types turned into configuration (PRD §7.7, D8): the Samurai8 result for the configured
 * accounts and questionnaires, the Livingood result for the configured accounts.
 */
final class ResultTypes
{
    public const SAMURAI8 = 'samurai8';
    public const LIVINGOOD = 'livingood';

    public function __construct(private readonly ResultTypeSettings $settings)
    {
    }

    /** The configured result type of a questionnaire (or of its chain's root), or null when it has none. */
    public function configuredFor(string $customerId, string ...$questionnaireIds): ?string
    {
        if (\in_array($customerId, $this->settings->samurai8CustomerIds(), true)
            || [] !== array_intersect($questionnaireIds, $this->settings->samurai8QuestionnaireIds())) {
            return self::SAMURAI8;
        }
        if (\in_array($customerId, $this->settings->livingoodCustomerIds(), true)) {
            return self::LIVINGOOD;
        }

        return null;
    }
}
