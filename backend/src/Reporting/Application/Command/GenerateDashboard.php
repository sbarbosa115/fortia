<?php

namespace App\Reporting\Application\Command;

/**
 * Chooses and stores a questionnaire's dashboard, once (PRD §7.10). Does nothing when it already has one;
 * 502 DASHBOARD_GENERATION_FAILED (and nothing stored) when the LLM fails or none of its charts survive.
 */
final class GenerateDashboard
{
    public function __construct(
        public readonly string $questionnaireId,
    ) {
    }
}
