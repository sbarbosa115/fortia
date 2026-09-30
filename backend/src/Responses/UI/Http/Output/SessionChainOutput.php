<?php

namespace App\Responses\UI\Http\Output;

use App\Shared\UI\Http\Output\Document\SessionOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** Every stage of a prompt chain a respondent went through (PRD §8.4 GET /questionnaire/session/{id}/chain). */
final class SessionChainOutput
{
    /** @param list<SessionOutput> $stages */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: SessionOutput::class)))]
        public readonly array $stages,
        public readonly int $total_stages,
    ) {
    }
}
