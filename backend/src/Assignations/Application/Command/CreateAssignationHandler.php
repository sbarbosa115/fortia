<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Error\InvalidAssignation;
use App\Assignations\Domain\Event\AssignationCreated;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateAssignationHandler
{
    public function __construct(
        private readonly AssignationRepository $assignations,
        private readonly AssignationConfiguration $configuration,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateAssignation $command): string
    {
        $fields = $command->fields;
        $type = (string) ($fields['type'] ?? '');
        if (!\in_array($type, [Assignation::DEFAULT, Assignation::FOLLOW_UP], true)) {
            throw new InvalidAssignation([['field' => 'type', 'message' => 'The type must be default or follow_up.']]);
        }
        // The assignation belongs to its organization's account (an Admin may create one for any account).
        $organization = $this->configuration->organization($command->caller, (string) ($fields['organization_id'] ?? ''));

        $now = $this->clock->now();
        $id = Ids::uuid4();
        $assignation = new Assignation($id, (string) $organization['customer_id'], (string) $organization['organization_id'], strtolower((string) ($fields['questionnaire_id'] ?? '')), trim((string) ($fields['name'] ?? '')), $type, $now);
        $this->configuration->apply($command->caller, $assignation, $fields + ['active' => true, 'audience' => null, 'max_follow_ups' => 0], $now);
        $this->assignations->add($assignation);
        $this->events->publish(AssignationCreated::of($assignation->customerId(), $id));

        return $id;
    }
}
