<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400: a "members" audience names people who are not members of the assignation's organization (PRD §8.8). */
final class AudienceMemberNotInOrganization extends Rejected
{
    /** @param list<string> $memberIds */
    public function __construct(array $memberIds)
    {
        parent::__construct('AUDIENCE_MEMBER_NOT_IN_ORGANIZATION', 'Some of the chosen people are not members of this organization.', ['organization_user_ids' => $memberIds]);
    }
}
