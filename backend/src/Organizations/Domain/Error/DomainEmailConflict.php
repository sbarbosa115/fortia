<?php

namespace App\Organizations\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409: another organization, of any account, already uses this email domain (PRD §6.12, §8.7). */
final class DomainEmailConflict extends Conflict
{
    public function __construct(string $domain)
    {
        parent::__construct('DOMAIN_EMAIL_CONFLICT', \sprintf('Another organization already uses the domain "%s".', $domain));
    }
}
