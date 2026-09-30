<?php

namespace App\Jobs\Application;

use App\Shared\Application\Message\AsyncMessage;

/** Tells the worker to run a job. */
final class RunJob implements AsyncMessage
{
    public function __construct(public readonly string $jobId)
    {
    }
}
