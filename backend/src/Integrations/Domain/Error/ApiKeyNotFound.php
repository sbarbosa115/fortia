<?php

namespace App\Integrations\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** 404: no such active key, or it belongs to another account (PRD §8.11). */
final class ApiKeyNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('API_KEY_NOT_FOUND', 'API key not found.');
    }
}
