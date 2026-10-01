<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Event\ProfileEdited;
use App\Identity\Domain\Model\SettingsChange;
use App\Identity\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangeSettingsHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(ChangeSettings $command): array
    {
        $customer = $this->customers->get($command->customerId);
        $change = SettingsChange::of($command->fields);
        $customer->changeSettings($change->changes(), $this->clock->now());
        $this->events->publish(ProfileEdited::of($command->customerId, $change->fields(), !$change->onlyLanguage()));

        return $customer->settings();
    }
}
