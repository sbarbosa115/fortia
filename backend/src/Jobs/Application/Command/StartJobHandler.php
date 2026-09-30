<?php

declare(strict_types=1);

namespace App\Jobs\Application\Command;

use App\Jobs\Application\Jobs;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class StartJobHandler
{
    public function __construct(private readonly Jobs $jobs)
    {
    }

    public function __invoke(StartJob $command): string
    {
        return $this->jobs->start($command->type, $command->payload, $command->customerId);
    }
}
