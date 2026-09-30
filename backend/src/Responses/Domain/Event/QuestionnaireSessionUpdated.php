<?php

namespace App\Responses\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12. Payload: {session_id, questionnaire_id}. */
final class QuestionnaireSessionUpdated extends BaseDomainEvent
{
    public static function of(string $customerId, string $sessionId, string $questionnaireId): self
    {
        return new self($customerId, null, ['session_id' => $sessionId, 'questionnaire_id' => $questionnaireId]);
    }
}
