<?php

declare(strict_types=1);

namespace App\Billing\Application\EventHandler;

use App\Billing\Application\Usage;
use App\Shared\Domain\Event\DomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** An event that carries a feature counts one unit of it (PRD §7.2, §12 "tags.feature"). */
#[AsMessageHandler(bus: 'event.bus', handles: DomainEvent::class)]
final class CountUsage
{
    public function __construct(private readonly Usage $usage)
    {
    }

    public function __invoke(DomainEvent $event): void
    {
        $customerId = $event->customerId();
        $feature = $event->feature();
        if (null !== $customerId && null !== $feature) {
            $this->usage->record($customerId, $feature);
        }
    }
}
