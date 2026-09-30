<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Identity\Domain\Repository\CustomerRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SetOnboardingHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SetOnboarding $command): void
    {
        $this->customers->get($command->customerId)->setOnboardingCompleted($command->completed, $this->clock->now());
    }
}
