<?php

namespace App\Generation\Application\Port;

use App\Shared\Domain\Error\UpstreamFailed;

/**
 * Reads a public LinkedIn profile (PRD §7.18, §13: a scraping provider). Dev and tests run on the fake
 * (LINKEDIN_PROVIDER=fake), which answers offline.
 */
interface LinkedinProfiles
{
    /** @throws UpstreamFailed LINKEDIN_PROFILE_UNAVAILABLE when the profile cannot be read */
    public function profile(string $url): LinkedinProfile;
}
