<?php

namespace App\Jobs\Infrastructure\Persistence;

use App\Jobs\Domain\Model\Job;
use App\Jobs\Domain\Repository\JobRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Job> */
final class DoctrineJobRepository extends DoctrineRepository implements JobRepository
{
    protected function entityClass(): string
    {
        return Job::class;
    }

    public function find(string $jobId): ?Job
    {
        return $this->findEntity($jobId);
    }

    public function add(Job $job): void
    {
        $this->persist($job);
    }
}
