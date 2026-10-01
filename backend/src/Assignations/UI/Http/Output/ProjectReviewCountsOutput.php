<?php

namespace App\Assignations\UI\Http\Output;

/** The reviews of the current attempt: "R of T reviewed", "N sent back to the client" (PRD §10.12). */
final class ProjectReviewCountsOutput
{
    public function __construct(
        public readonly int $reviewed,
        public readonly int $total,
        public readonly int $approved,
        public readonly int $rejected,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function of(array $data): self
    {
        return new self((int) $data['reviewed'], (int) $data['total'], (int) $data['approved'], (int) $data['rejected']);
    }
}
