<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * Creates an assignation (PRD §8.8 POST /assignations) and returns its id. $fields has the PRD's names:
 * organization_id, questionnaire_id, name, description?, max_follow_ups, active (default true), type, due_date?
 * (follow-up only), audience (default {type: all}), questions (≥ 1, the registration slide).
 */
final class CreateAssignation
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public readonly Caller $caller,
        public readonly array $fields,
    ) {
    }
}
