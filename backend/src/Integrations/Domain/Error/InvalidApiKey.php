<?php

namespace App\Integrations\Domain\Error;

use App\Shared\Domain\Error\Unauthenticated;

/** 401 on the external API: the same answer whether the key is missing, unknown, revoked or expired (PRD §8.11). */
final class InvalidApiKey extends Unauthenticated
{
    public function __construct()
    {
        parent::__construct('INVALID_API_KEY', 'Invalid API key.');
    }
}
