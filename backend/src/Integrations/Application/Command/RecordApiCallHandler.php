<?php

namespace App\Integrations\Application\Command;

use App\Integrations\Domain\Error\InvalidApiKey;
use App\Integrations\Domain\Event\ApiUsage;
use App\Integrations\Domain\Repository\ApiKeyRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RecordApiCallHandler
{
    public function __construct(
        private readonly ApiKeyRepository $keys,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RecordApiCall $command): void
    {
        $key = $this->keys->find($command->apiKeyId);
        if (null === $key) {
            throw new InvalidApiKey();
        }
        $key->recordUse($this->clock->now());
        $this->events->publish(ApiUsage::of($key->customerId(), $key->id(), $command->endpoint));
    }
}
