<?php

namespace App\Commerce\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** The OAuth callback's state is missing, forged, expired or for another shop (D5): 400 INVALID_REQUEST (§8.6). */
final class InvalidOAuthState extends Rejected
{
    public function __construct()
    {
        parent::__construct('INVALID_REQUEST', 'The connection request is invalid or expired. Start the connection again.');
    }
}
