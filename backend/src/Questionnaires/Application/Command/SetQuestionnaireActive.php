<?php

namespace App\Questionnaires\Application\Command;

/**
 * Activates or deactivates a questionnaire (PRD §8.4 PATCH /questionnaire/{id}). Deactivating is the questionnaires'
 * soft delete (§6): an inactive questionnaire starts no new sessions.
 */
final class SetQuestionnaireActive
{
    public function __construct(
        public readonly string $questionnaireId,
        public readonly bool $active,
    ) {
    }
}
