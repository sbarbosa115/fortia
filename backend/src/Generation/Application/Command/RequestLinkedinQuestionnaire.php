<?php

namespace App\Generation\Application\Command;

/**
 * POST /questionnaire/linkedin (PRD §8.4, §7.18): a diagnostic questionnaire generated from a LinkedIn profile, owned
 * by the account configured as its owner (D8). Starts the `linkedin_questionnaire` job and returns its id.
 */
final class RequestLinkedinQuestionnaire
{
    public function __construct(
        public readonly string $linkedinUrl,
        /** en or es */
        public readonly string $language,
    ) {
    }
}
