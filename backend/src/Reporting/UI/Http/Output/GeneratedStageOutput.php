<?php

namespace App\Reporting\UI\Http\Output;

/** A generated child stage of a chain (PRD §7.8, the "Generated questionnaires" card of §10.8). */
final class GeneratedStageOutput
{
    public function __construct(
        public readonly string $questionnaire_id,
        public readonly string $title,
        public readonly ?string $origin_session_id,
        public readonly ?string $created_at,
    ) {
    }
}
