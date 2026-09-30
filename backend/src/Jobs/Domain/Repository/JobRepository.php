<?php

namespace App\Jobs\Domain\Repository;

use App\Jobs\Domain\Model\Job;

interface JobRepository
{
    public function find(string $jobId): ?Job;

    public function add(Job $job): void;
}
