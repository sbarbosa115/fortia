<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Application\OwnedAssignations;
use App\Assignations\Domain\Error\InvalidAssignation;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateAssignationHandler
{
    public function __construct(
        private readonly OwnedAssignations $owned,
        private readonly AssignationConfiguration $configuration,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(UpdateAssignation $command): void
    {
        $assignation = $this->owned->get($command->caller, $command->assignationsId);
        if (\array_key_exists('type', $command->fields)) {
            throw new InvalidAssignation([['field' => 'type', 'message' => 'type cannot be changed']]);
        }
        $this->configuration->apply($command->caller, $assignation, $command->fields, $this->clock->now());
    }
}
