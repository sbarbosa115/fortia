<?php

namespace App\Responses\UI\Http\Output;

use App\Shared\UI\Http\Output\Document\CtaOutput;
use App\Shared\UI\Http\Output\Document\FlowOutput;
use OpenApi\Attributes as OA;

/**
 * What a submitted session answers when its result is computed right away (PRD §8.4 POST /questionnaire/session):
 * {type, …result, cta?, layout?, result_copy?}. The result's own fields follow its type: a diagnostic's are those of
 * DiagnosticResultOutput (score, categories, tiers, recommendations, action_plan); ai_team_profile, samurai8 and
 * livingood carry their report (the same object GET …/results returns in ai_team_profile or extra).
 */
#[OA\Schema(additionalProperties: true)]
final class SessionSubmissionOutput
{
    /**
     * @param list<string>|null          $layout
     * @param array<string, string>|null $result_copy
     */
    public function __construct(
        #[OA\Property(enum: ['default', 'diagnostic', 'ai_team_profile', 'samurai8', 'livingood'])]
        public readonly string $type,
        public readonly ?CtaOutput $cta = null,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(type: 'string', enum: FlowOutput::LAYOUT_BLOCKS))]
        public readonly ?array $layout = null,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties(type: 'string'))]
        public readonly ?array $result_copy = null,
    ) {
    }
}
