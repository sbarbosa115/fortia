<?php

namespace App\Reporting\UI\Http\Output;

/** The chain stage a respondent reached ("Stage X of N", PRD §10.8). */
final class AnswerChainOutput
{
    public function __construct(
        public readonly int $stage,
        public readonly int $total_stages,
    ) {
    }
}
