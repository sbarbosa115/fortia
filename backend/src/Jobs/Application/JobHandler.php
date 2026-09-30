<?php

declare(strict_types=1);

namespace App\Jobs\Application;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Does the work of one job type (PRD §6.17) on the worker. A context adds one per job type it owns, in its
 * Application/Job folder. It returns the job's result (PRD: e.g. {type: "create_quiz_funnel", flow, …}); throwing
 * fails the job with {error: {type, message}} (a DomainError's code is the type).
 *
 * It runs outside any transaction: writes go through the command bus, one command each.
 */
#[AutoconfigureTag('app.job_handler')]
interface JobHandler
{
    /** The job_type it handles. */
    public function type(): string;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function handle(array $payload, JobProgress $progress): array;
}
