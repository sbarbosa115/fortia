<?php

namespace App\Reporting\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12, §7.2: analytics or dashboard data were queried; counts one unit of "analytics". Payload: {questionnaire_id, view}. */
final class AnalyticsFetched extends BaseDomainEvent
{
    public static function of(string $customerId, string $questionnaireId, string $view): self
    {
        return new self($customerId, 'analytics', ['questionnaire_id' => $questionnaireId, 'view' => $view]);
    }
}
