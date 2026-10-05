<?php

namespace App\Shared\Application\Analytics;

/** The usage/analytics service an account's events go to (AnalyticsEndpoint::resolve), or null when none is set. */
interface AnalyticsEndpoints
{
    public function endpointFor(?string $customerId): ?AnalyticsEndpoint;
}
