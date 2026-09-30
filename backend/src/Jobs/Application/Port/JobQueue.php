<?php

namespace App\Jobs\Application\Port;

/** Hands a job to the worker once the current command's transaction has committed. */
interface JobQueue
{
    public function enqueue(string $jobId): void;
}
