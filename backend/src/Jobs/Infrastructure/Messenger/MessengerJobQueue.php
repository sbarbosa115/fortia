<?php

namespace App\Jobs\Infrastructure\Messenger;

use App\Jobs\Application\Port\JobQueue;
use App\Jobs\Application\RunJob;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final class MessengerJobQueue implements JobQueue
{
    public function __construct(
        #[Autowire(service: 'job.bus')]
        private readonly MessageBusInterface $jobBus,
    ) {
    }

    public function enqueue(string $jobId): void
    {
        $this->jobBus->dispatch(new RunJob($jobId), [new DispatchAfterCurrentBusStamp()]);
    }
}
