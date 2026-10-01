<?php

namespace App\Assignations\UI\Http\Output;

use OpenApi\Attributes as OA;

/** Who answers (PRD §6.14): everybody, chosen members (their ids), or the members of some areas or roles. */
final class AudienceOutput
{
    /** @param list<string> $values */
    public function __construct(
        #[OA\Property(enum: ['all', 'members', 'area', 'role'])]
        public readonly string $type,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
        public readonly array $values,
    ) {
    }
}
