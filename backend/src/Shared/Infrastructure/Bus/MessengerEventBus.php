<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Event\DomainEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final class MessengerEventBus implements EventBus
{
    public function __construct(
        #[Autowire(service: 'event.bus')]
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            // Inside a command handler: dispatched after that command's transaction commits.
            $this->bus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
