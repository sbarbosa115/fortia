<?php

namespace App\Reporting\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12, §7.2: a questionnaire's dashboard was chosen (once); counts one unit of "dashboards". Payload: {questionnaire_id, type}. */
final class DashboardGenerated extends BaseDomainEvent
{
    public static function of(string $customerId, string $questionnaireId, string $type): self
    {
        return new self($customerId, 'dashboards', ['questionnaire_id' => $questionnaireId, 'type' => $type]);
    }
}
