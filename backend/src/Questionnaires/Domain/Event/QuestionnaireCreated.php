<?php

namespace App\Questionnaires\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/**
 * PRD §12. A generated chain stage emits none. Payload: {questionnaire_id, source} (source: console, copy,
 * quiz_funnel, chat, linkedin).
 */
final class QuestionnaireCreated extends BaseDomainEvent
{
    public static function of(string $customerId, string $questionnaireId, string $source): self
    {
        return new self($customerId, ['questionnaire_id' => $questionnaireId, 'source' => $source]);
    }
}
