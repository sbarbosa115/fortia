<?php

declare(strict_types=1);

namespace App\Jobs\UI\Http\Output;

/** {job: {...}}: how GET /jobs/{id} and every endpoint that starts a job answer (PRD §8.4, §8.5). */
final class JobEnvelopeOutput
{
    public function __construct(public readonly JobOutput $job)
    {
    }
}
