<?php

namespace App\Integrations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /external/questionnaires (PRD §8.11): {questionnaires, pagination}. */
final class ExternalQuestionnaireListOutput
{
    /** @param list<ExternalQuestionnaireOutput> $questionnaires */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ExternalQuestionnaireOutput::class)))]
        public readonly array $questionnaires,
        public readonly ExternalPaginationOutput $pagination,
    ) {
    }
}
