<?php

namespace App\Assignations\UI\Http\Output;

/** A follow-up of the project's organization that may belong to it (not in another project). */
final class ProjectAvailableAssignationOutput
{
    public function __construct(
        public readonly string $assignations_id,
        public readonly string $name,
        /** This project's id when it already belongs to it, else null. */
        public readonly ?string $project_id,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function of(array $data): self
    {
        return new self((string) $data['assignations_id'], (string) $data['name'], null === $data['project_id'] ? null : (string) $data['project_id']);
    }
}
