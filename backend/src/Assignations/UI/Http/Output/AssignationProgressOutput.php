<?php

namespace App\Assignations\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * Progress (PRD §7.11): a default assignation counts audience members with a response ("respondents"); a follow-up
 * counts answerable questions answered or skipped ("questions", with the current question, 1-based).
 */
final class AssignationProgressOutput
{
    public function __construct(
        public readonly int $completed,
        public readonly int $total,
        #[OA\Property(enum: ['respondents', 'questions'])]
        public readonly string $unit,
        /** Follow-ups: the first answerable question not answered or skipped; null when none is left. */
        public readonly ?int $current_question,
    ) {
    }

    /** @param array<string, mixed> $p */
    public static function of(array $p): self
    {
        return new self((int) $p['completed'], (int) $p['total'], (string) $p['unit'], null === $p['current_question'] ? null : (int) $p['current_question']);
    }
}
