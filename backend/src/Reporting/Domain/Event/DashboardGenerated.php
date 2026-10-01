<?php

namespace App\Reporting\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12: a questionnaire's dashboard was chosen (once). Payload: {questionnaire_id, type}. */
final class DashboardGenerated extends BaseDomainEvent
{
    public static function of(string $customerId, string $questionnaireId, string $type): self
    {
        return new self($customerId, ['questionnaire_id' => $questionnaireId, 'type' => $type]);
    }
}
