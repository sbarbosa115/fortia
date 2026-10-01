<?php

namespace App\Responses\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/**
 * A respondent finished a session and its result was computed (PRD §7.7 step 3, §12). final_stage is true on the
 * final stage of a chain or a standalone questionnaire. Payload: {session_id, questionnaire_id,
 * root_questionnaire_id, final_stage: bool}.
 */
final class QuestionnaireSessionCompleted extends BaseDomainEvent
{
    public static function of(string $customerId, string $sessionId, string $questionnaireId, string $rootQuestionnaireId, bool $finalStage): self
    {
        return new self($customerId, [
            'session_id' => $sessionId,
            'questionnaire_id' => $questionnaireId,
            'root_questionnaire_id' => $rootQuestionnaireId,
            'final_stage' => $finalStage,
        ]);
    }

    public function sessionId(): string
    {
        return (string) $this->payload()['session_id'];
    }

    public function questionnaireId(): string
    {
        return (string) $this->payload()['questionnaire_id'];
    }

    public function isFinalStage(): bool
    {
        return (bool) $this->payload()['final_stage'];
    }
}
