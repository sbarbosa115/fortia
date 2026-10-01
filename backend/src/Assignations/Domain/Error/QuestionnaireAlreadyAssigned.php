<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409: a questionnaire is assigned to one organization only (PRD §6.14); details name the one that has it. */
final class QuestionnaireAlreadyAssigned extends Conflict
{
    public function __construct(string $organizationId, string $organizationName)
    {
        parent::__construct('QUESTIONNAIRE_ALREADY_ASSIGNED', 'This questionnaire is already assigned to another organization.', [
            'organization_id' => $organizationId,
            'organization_name' => $organizationName,
        ]);
    }
}
