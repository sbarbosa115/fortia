<?php

namespace App\Generation\Infrastructure\Linkedin;

use App\Generation\Application\Port\LinkedinProfile;
use App\Generation\Application\Port\LinkedinProfiles;
use App\Generation\Domain\LinkedinRequest;
use App\Shared\Domain\Error\UpstreamFailed;

/**
 * The offline LinkedIn reader (dev and tests): a profile made up from the URL's handle, always the same for the same
 * URL. The handle "unavailable" (…/in/unavailable) fails like a profile that cannot be read.
 */
final class FakeLinkedinProfiles implements LinkedinProfiles
{
    public function profile(string $url): LinkedinProfile
    {
        $handle = LinkedinRequest::handle($url);
        if ('' === $handle || 'unavailable' === strtolower($handle)) {
            throw new UpstreamFailed('LINKEDIN_PROFILE_UNAVAILABLE', 'The LinkedIn profile could not be read.');
        }
        $name = ucwords(trim((string) preg_replace('/[^\pL]+/u', ' ', $handle)));

        return new LinkedinProfile(
            $url,
            '' === $name ? 'LinkedIn member' : $name,
            'Head of Operations at Example Logistics',
            'Bogotá, Colombia',
            'Leads a team of 40 people running warehouses and last-mile delivery.',
            ['Head of Operations at Example Logistics, 2021–present', 'Operations Manager at Sample Retail, 2016–2021'],
            ['Process improvement', 'Team leadership', 'Supply chain'],
        );
    }
}
