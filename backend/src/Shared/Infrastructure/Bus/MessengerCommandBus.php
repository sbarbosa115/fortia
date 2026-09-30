<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class MessengerCommandBus implements CommandBus
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function dispatch(object $command): mixed
    {
        try {
            $envelope = $this->bus->dispatch($command);
        } catch (HandlerFailedException $e) {
            // Let the domain's own error (and its HTTP mapping) through instead of Messenger's wrapper.
            $previous = $e->getPrevious();
            throw null !== $previous ? $previous : $e;
        }

        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
