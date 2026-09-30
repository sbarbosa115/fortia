<?php

namespace App\Questionnaires\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/**
 * PRD §12, §7.2: counts one unit of the feature of its type (regular, diagnostic, quiz-funnel, chain or chat;
 * LinkedIn counts as diagnostic). A generated chain stage emits none. Payload: {questionnaire_id, source}
 * (source: console, copy, quiz_funnel, chat, linkedin).
 */
final class QuestionnaireCreated extends BaseDomainEvent
{
    public static function of(string $customerId, string $questionnaireId, string $feature, string $source): self
    {
        return new self($customerId, $feature, ['questionnaire_id' => $questionnaireId, 'source' => $source]);
    }
}
