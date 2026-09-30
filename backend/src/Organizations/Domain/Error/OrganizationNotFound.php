<?php

namespace App\Organizations\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** 404: no such organization, or it belongs to another account (D1: another tenant's id is 404, never 403). */
final class OrganizationNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('ORGANIZATION_NOT_FOUND', 'Organization not found.');
    }
}
