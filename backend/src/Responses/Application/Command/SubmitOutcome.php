<?php

namespace App\Responses\Application\Command;

/**
 * What a submission answers (PRD §8.4): the job that computes the result (quiz funnel), or the result
 * {type, …result, cta?, layout?, result_copy?}.
 */
final class SubmitOutcome
{
    /** @param array<string, mixed>|null $result */
    private function __construct(
        public readonly ?string $jobId,
        public readonly ?array $result,
    ) {
    }

    public static function job(string $jobId): self
    {
        return new self($jobId, null);
    }

    /** @param array<string, mixed> $result */
    public static function result(array $result): self
    {
        return new self(null, $result);
    }
}
