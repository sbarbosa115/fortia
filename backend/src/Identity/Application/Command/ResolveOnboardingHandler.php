<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Repository\CustomerRepository;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ResolveOnboardingHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly QuestionnaireQueries $questionnaires,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ResolveOnboarding $command): bool
    {
        $customer = $this->customers->find($command->customerId);
        if (null === $customer) {
            return true;
        }
        $completed = $customer->onboardingCompleted();
        if (null === $completed) {
            $completed = $this->questionnaires->countRootsOf($command->customerId) > 0;
            $customer->setOnboardingCompleted($completed, $this->clock->now());
        }

        return $completed;
    }
}
