<?php

namespace App\Assignations\UI\Http\Output;

/** POST /assignations (PRD §8.8): the respondent link ({FRONTEND_URL}/a/{id}) and the new id. */
final class AssignationCreatedOutput
{
    public function __construct(
        public readonly string $questionnaire_url,
        public readonly string $assignation_id,
    ) {
    }
}
