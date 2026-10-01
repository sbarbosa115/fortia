<?php

namespace App\Reporting\Domain\Error;

use App\Shared\Domain\Error\UpstreamFailed;

/** PRD §8.4 GET /dashboard/data: the data could not be computed (502). */
final class AnalyticsUnavailable extends UpstreamFailed
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('ANALYTICS_UNAVAILABLE', 'The analytics are unavailable right now.', [], $previous);
    }
}
