<?php

namespace App\Assignations\UI\Http\Output;

/** A follow-up's progress (PRD §7.11): answerable questions answered or skipped of the total; "Question 4 of 8". */
final class ProjectAssignationProgressOutput
{
    public function __construct(
        public readonly int $completed,
        public readonly int $total,
        /** Always "questions" for a follow-up. */
        public readonly string $unit,
        /** 1-based; null when every question is answered or skipped. */
        public readonly ?int $current_question,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function of(array $data): self
    {
        return new self((int) $data['completed'], (int) $data['total'], (string) $data['unit'], null === $data['current_question'] ? null : (int) $data['current_question']);
    }
}
