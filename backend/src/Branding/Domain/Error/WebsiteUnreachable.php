<?php

namespace App\Branding\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** The styles job could not read the account's website (PRD §7.16 step 1): it fails, and nothing is saved or counted. */
final class WebsiteUnreachable extends Rejected
{
    public function __construct(string $website)
    {
        parent::__construct('WEBSITE_UNREACHABLE', \sprintf('We could not read %s. Check the address and try again.', $website));
    }
}
