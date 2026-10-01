<?php

namespace App\Content\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400: GET /videos?language= is neither es nor en (PRD §8.12). */
final class InvalidLanguage extends Rejected
{
    public function __construct()
    {
        parent::__construct('INVALID_LANGUAGE', 'The language must be "es" or "en".');
    }
}
