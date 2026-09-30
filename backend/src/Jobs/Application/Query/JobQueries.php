<?php

namespace App\Jobs\Application\Query;

use App\Jobs\Domain\Repository\JobRepository;

/**
 * Reads jobs for other contexts' controllers: after starting a job they answer 202 {job} with its current state.
 */
final class JobQueries
{
    public function __construct(private readonly JobRepository $jobs)
    {
    }

    /** @return array{job_id: string, job_type: string, status: string, result: array<string, mixed>|null, stage: string|null, created_at: string, updated_at: string}|null */
    public function find(string $jobId): ?array
    {
        $job = $this->jobs->find($jobId);
        if (null === $job) {
            return null;
        }

        return [
            'job_id' => $job->jobId(),
            'job_type' => $job->jobType(),
            'status' => $job->status(),
            'result' => $job->result(),
            'stage' => $job->stage(),
            'created_at' => $job->createdAt()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $job->updatedAt()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
