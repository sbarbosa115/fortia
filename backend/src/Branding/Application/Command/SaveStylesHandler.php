<?php

namespace App\Branding\Application\Command;

use App\Branding\Domain\Event\BrandStylesUpdated;
use App\Branding\Domain\Model\CustomerStyles;
use App\Branding\Domain\Repository\CustomerStylesRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Creates or replaces the account's styles and counts one "styles" (BrandStylesUpdated, PRD §7.2). */
#[AsMessageHandler(bus: 'command.bus')]
final class SaveStylesHandler
{
    public function __construct(
        private readonly CustomerStylesRepository $styles,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SaveStyles $command): string
    {
        $stored = $this->styles->find($command->customerId);
        if (null === $stored) {
            $this->styles->add(new CustomerStyles($command->customerId, $command->website, $command->styles, $this->clock->now()));
        } else {
            $stored->restyle($command->website, $command->styles, $this->clock->now());
        }
        $this->events->publish(BrandStylesUpdated::of($command->customerId, $command->website, $command->fromWebsite));

        return $command->customerId;
    }
}
