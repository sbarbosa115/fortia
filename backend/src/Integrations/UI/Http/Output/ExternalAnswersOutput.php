<?php

namespace App\Integrations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /external/questionnaires/{id}/answers (PRD §8.11): {questionnaire_id, sessions, pagination}, newest first. */
final class ExternalAnswersOutput
{
    /** @param list<ExternalSessionOutput> $sessions */
    public function __construct(
        public readonly string $questionnaire_id,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ExternalSessionOutput::class)))]
        public readonly array $sessions,
        public readonly ExternalPaginationOutput $pagination,
    ) {
    }
}
