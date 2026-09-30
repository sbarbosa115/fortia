<?php

namespace App\Jobs\UI\Http\Output;

use App\Jobs\Domain\Model\Job;
use App\Shared\Domain\Iso;
use OpenApi\Attributes as OA;

/** A job as the apps poll it (PRD §8.5). Never the payload. */
final class JobOutput
{
    /**
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        public readonly string $job_id,
        #[OA\Property(enum: Job::TYPES)]
        public readonly string $job_type,
        #[OA\Property(enum: [Job::PENDING, Job::PROCESSING, Job::COMPLETED, Job::FAILED, Job::CANCELLED])]
        public readonly string $status,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: true)]
        public readonly ?array $result,
        public readonly ?string $stage,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {
    }

    public static function of(Job $job): self
    {
        return new self(
            $job->jobId(),
            $job->jobType(),
            $job->status(),
            $job->result(),
            $job->stage(),
            (string) Iso::datetime($job->createdAt()),
            (string) Iso::datetime($job->updatedAt()),
        );
    }
}
