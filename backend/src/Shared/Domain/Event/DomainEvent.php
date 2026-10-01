<?php

namespace App\Shared\Domain\Event;

/**
 * Something that happened, named as in PRD §12 (UserRootRegistered, QuestionnaireCreated…). Published on the
 * event bus after the command that caused it commits, and handled on the worker:
 *
 * - every event is recorded in the analytics event log (the analytics service absorbed, PRD §13.8);
 * - contexts subscribe to the ones they care about (emails…).
 *
 * Events are routed to the async transport (config/packages/messenger.yaml).
 * Keep events small and serializable: scalars and arrays only.
 */
interface DomainEvent
{
    /** The PRD §12 name, e.g. "QuestionnaireCreated". */
    public function eventType(): string;

    /** The account it belongs to, or null for platform events. */
    public function customerId(): ?string;

    /** @return array<string, mixed> */
    public function payload(): array;
}
