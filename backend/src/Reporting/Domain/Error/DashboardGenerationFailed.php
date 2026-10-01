<?php

namespace App\Reporting\Domain\Error;

use App\Shared\Domain\Error\UpstreamFailed;

/** PRD §7.10: the LLM failed or no chart of its choice survived the cleanup (502, nothing is stored). */
final class DashboardGenerationFailed extends UpstreamFailed
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('DASHBOARD_GENERATION_FAILED', 'The dashboard could not be generated.', [], $previous);
    }
}
