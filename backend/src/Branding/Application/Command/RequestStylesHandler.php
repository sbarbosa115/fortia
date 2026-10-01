<?php

namespace App\Branding\Application\Command;

use App\Branding\Application\Job\StylesJob;
use App\Jobs\Application\Jobs;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Starts the styles job (PRD §7.16); returns its id. The job decides whether the website changed when it runs. */
#[AsMessageHandler(bus: 'command.bus')]
final class RequestStylesHandler
{
    public function __construct(private readonly Jobs $jobs)
    {
    }

    public function __invoke(RequestStyles $command): string
    {
        return $this->jobs->start(StylesJob::TYPE, [
            'customer_id' => $command->customerId,
            'website' => $command->website,
            'styles' => $command->styles,
        ], $command->customerId);
    }
}
