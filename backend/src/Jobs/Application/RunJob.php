<?php

declare(strict_types=1);

namespace App\Jobs\Application;

use App\Shared\Application\Message\AsyncMessage;

/** Tells the worker to run a job. */
final class RunJob implements AsyncMessage
{
    public function __construct(public readonly string $jobId)
    {
    }
}
