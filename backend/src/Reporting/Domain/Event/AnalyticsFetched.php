<?php

namespace App\Reporting\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12: analytics or dashboard data were queried. Payload: {questionnaire_id, view}. */
final class AnalyticsFetched extends BaseDomainEvent
{
    public static function of(string $customerId, string $questionnaireId, string $view): self
    {
        return new self($customerId, ['questionnaire_id' => $questionnaireId, 'view' => $view]);
    }
}
