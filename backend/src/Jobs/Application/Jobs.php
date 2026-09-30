<?php

namespace App\Jobs\Application;

use App\Jobs\Application\Port\JobQueue;
use App\Jobs\Domain\Model\Job;
use App\Jobs\Domain\Repository\JobRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;

/**
 * Starts jobs (PRD §5 A1: anything slower than a request runs as a job the apps poll with GET /jobs/{id}).
 *
 * Call start() from inside a command handler: the job row is written in that command's transaction and the worker
 * is told to run it once the transaction commits. A controller with nothing else to write dispatches StartJob.
 */
final class Jobs
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly Clock $clock,
        private readonly JobQueue $queue,
    ) {
    }

    /**
     * @param array<string, mixed> $payload internal, never returned to anyone
     *
     * @return string the job id
     */
    public function start(string $type, array $payload, ?string $customerId = null): string
    {
        $job = new Job(Ids::jobId($this->clock->now()), $type, $payload, $customerId, $this->clock->now());
        $this->jobs->add($job);
        $this->queue->enqueue($job->jobId());

        return $job->jobId();
    }
}
