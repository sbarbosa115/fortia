<?php

namespace App\Questionnaires\UI\Http\Output;

use OpenApi\Attributes as OA;

/** GET /questionnaire/tags: {tags: [...]}, every tag of the account's questionnaires once, sorted. */
final class QuestionnaireTagsOutput
{
    /** @param list<string> $tags */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
        public readonly array $tags,
    ) {
    }
}
