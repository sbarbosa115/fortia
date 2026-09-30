<?php

namespace App\Shared\UI\Http\Request;

/**
 * An Input DTO for a partial update needs to tell "not sent" from "sent as null" (PRD: "due_date: null clears it").
 * Implement this with ProvidedFieldsTrait and ask wasProvided('due_date').
 */
interface TracksProvidedFields
{
    /** @param list<string> $keys */
    public function markProvided(array $keys): void;

    public function wasProvided(string $key): bool;

    /** @return list<string> */
    public function providedFields(): array;
}
