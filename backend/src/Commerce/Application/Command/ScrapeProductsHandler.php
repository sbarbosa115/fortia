<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Application\Job\ScrapeProductsJob;
use App\Jobs\Application\Jobs;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ScrapeProductsHandler
{
    public function __construct(private readonly Jobs $jobs)
    {
    }

    public function __invoke(ScrapeProducts $command): string
    {
        return $this->jobs->start(ScrapeProductsJob::TYPE, ['url' => $command->url, 'limit' => $command->limit], $command->customerId);
    }
}
